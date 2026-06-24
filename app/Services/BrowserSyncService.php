<?php

namespace App\Services;

use App\Enums\TabCommandStatus;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\NormalizedBookmark;
use App\Models\TabCommand;
use App\Models\TabSnapshot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrowserSyncService
{
    public function __construct(private UrlSanitizer $urlSanitizer) {}

    /**
     * @param  array{name: string, browser: string, platform: string}  $attributes
     */
    public function registerDevice(?string $uuid, array $attributes): Device
    {
        $uuid ??= (string) Str::uuid();
        $isNewDevice = ! Device::query()->where('uuid', $uuid)->exists();

        if ($isNewDevice && Device::query()->count() >= (int) config('browserbridge.max_devices')) {
            throw ValidationException::withMessages([
                'device_uuid' => 'The maximum number of registered devices has been reached.',
            ]);
        }

        return Device::query()->updateOrCreate(
            ['uuid' => $uuid],
            [
                'name' => $attributes['name'],
                'browser' => $attributes['browser'],
                'platform' => $attributes['platform'],
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeBookmarkSnapshot(Device $device, array $payload): BookmarkSnapshot
    {
        return DB::transaction(function () use ($device, $payload): BookmarkSnapshot {
            $items = $this->normalizeBookmarkItems($payload['items'] ?? []);

            $snapshot = $device->bookmarkSnapshots()->create([
                'payload_json' => ['items' => $items],
                'encrypted_payload' => null,
                'item_count' => count($items),
            ]);

            $this->replaceNormalizedBookmarks($device, $items);

            return $snapshot;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeTabSnapshot(Device $device, array $payload): TabSnapshot
    {
        $tabs = $this->urlSanitizer->filterSyncableItems($payload['tabs'] ?? []);

        return $device->tabSnapshots()->create([
            'payload_json' => ['tabs' => $tabs],
            'encrypted_payload' => null,
            'tab_count' => count($tabs),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBookmarkItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => $this->isSyncableBookmarkItem($item))
            ->map(function (array $item): array {
                $type = Arr::get($item, 'type') === 'folder' ? 'folder' : 'bookmark';
                $path = collect(Arr::get($item, 'path', []))
                    ->filter(fn ($segment): bool => is_string($segment) && $segment !== '')
                    ->values()
                    ->all();

                return [
                    'external_id' => Arr::get($item, 'external_id', Arr::get($item, 'id')) ?: $this->bookmarkFallbackExternalId($item),
                    'parent_external_id' => Arr::get($item, 'parent_external_id', Arr::get($item, 'parentId')),
                    'type' => $type,
                    'title' => Arr::get($item, 'title'),
                    'url' => $type === 'bookmark' ? Arr::get($item, 'url') : null,
                    'path' => $path,
                    'date_added' => Arr::get($item, 'date_added'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isSyncableBookmarkItem(array $item): bool
    {
        $type = Arr::get($item, 'type');
        $url = Arr::get($item, 'url');

        if ($type === 'folder') {
            return true;
        }

        return is_string($url) && $this->urlSanitizer->isSyncableUrl($url);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function bookmarkFallbackExternalId(array $item): string
    {
        return sha1(json_encode([
            Arr::get($item, 'type', Arr::get($item, 'url') ? 'bookmark' : 'folder'),
            Arr::get($item, 'title'),
            Arr::get($item, 'url'),
            Arr::get($item, 'path', []),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceNormalizedBookmarks(Device $device, array $items): void
    {
        $externalIds = collect($items)->pluck('external_id')->filter()->values();

        foreach ($items as $item) {
            NormalizedBookmark::query()->updateOrCreate(
                [
                    'device_id' => $device->id,
                    'external_id' => $item['external_id'],
                ],
                [
                    'parent_external_id' => $item['parent_external_id'],
                    'type' => $item['type'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'path_json' => $item['path'],
                    'date_added' => $item['date_added'] ? Carbon::parse($item['date_added']) : null,
                ],
            );
        }

        $device->normalizedBookmarks()
            ->whereNotIn('external_id', $externalIds)
            ->delete();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    public function storeHistoryBatch(Device $device, array $items): array
    {
        return DB::transaction(function () use ($device, $items): array {
            $received = count($items);
            $stored = 0;
            $skipped = 0;
            $skippedReasons = [];

            $validItems = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skipped++;
                    $skippedReasons['invalid_item'] = ($skippedReasons['invalid_item'] ?? 0) + 1;
                    continue;
                }

                $validator = Validator::make($item, [
                    'url' => ['required', 'string', 'max:2048'],
                    'title' => ['nullable', 'string', 'max:512'],
                    'visited_at' => ['required', 'date'],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $failedRules = $validator->failed();
                    $reason = 'invalid_item';

                    if (isset($failedRules['url']['Max'])) {
                        $reason = 'url_too_long';
                    } elseif (isset($failedRules['title']['Max'])) {
                        $reason = 'title_too_long';
                    } elseif (isset($failedRules['url']['Required']) || isset($failedRules['url']['String'])) {
                        $reason = 'invalid_url';
                    } elseif (isset($failedRules['visited_at'])) {
                        $reason = 'missing_visit_time';
                    }

                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
                    continue;
                }

                if (! $this->urlSanitizer->isSyncableUrl($item['url'])) {
                    $skipped++;
                    $reason = $this->urlSanitizer->isBlockedInternalUrl($item['url']) ? 'internal_url' : 'invalid_url';
                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
                    continue;
                }

                $validItems[] = $item;
            }

            $syncableItems = collect($validItems)
                ->unique(fn (array $item): string => $item['url'].'|'.Carbon::parse($item['visited_at'])->toJSON())
                ->values()
                ->all();

            $maxItemsPerDevice = (int) config('browserbridge.max_history_items_per_device');

            if ($device->historyItems()->count() + count($syncableItems) > $maxItemsPerDevice) {
                throw ValidationException::withMessages([
                    'items' => "The device may store at most {$maxItemsPerDevice} history items.",
                ]);
            }

            foreach ($syncableItems as $item) {
                $historyItem = $device->historyItems()->firstOrCreate(
                    [
                        'url' => $item['url'],
                        'visited_at' => Carbon::parse($item['visited_at']),
                    ],
                    [
                        'title' => $item['title'] ?? null,
                        'encrypted_payload' => null,
                    ],
                );

                if ($historyItem->wasRecentlyCreated) {
                    $stored++;
                }
            }

            return [
                'received' => $received,
                'stored' => $stored,
                'skipped' => $skipped,
                'skipped_reasons' => (object) $skippedReasons,
            ];
        });
    }

    public function sendTabCommand(Device $sourceDevice, Device $targetDevice, string $url, ?string $title): TabCommand
    {
        $pendingCommandCount = $targetDevice->incomingTabCommands()
            ->where('status', TabCommandStatus::Pending)
            ->count();

        if ($pendingCommandCount >= (int) config('browserbridge.max_pending_tab_commands_per_target')) {
            throw ValidationException::withMessages([
                'target_device_uuid' => 'The target device has too many pending tab commands.',
            ]);
        }

        return TabCommand::query()->create([
            'source_device_id' => $sourceDevice->id,
            'target_device_id' => $targetDevice->id,
            'url' => $url,
            'title' => $title,
            'encrypted_payload' => null,
            'status' => TabCommandStatus::Pending,
        ]);
    }

    /**
     * @return EloquentCollection<int, TabCommand>
     */
    public function incomingTabCommands(Device $targetDevice): EloquentCollection
    {
        return $targetDevice->incomingTabCommands()
            ->with('sourceDevice')
            ->where('status', TabCommandStatus::Pending)
            ->latest()
            ->get();
    }
}
