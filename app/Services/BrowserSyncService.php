<?php

namespace App\Services;

use App\Enums\TabCommandStatus;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\TabCommand;
use App\Models\TabSnapshot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $items = $this->urlSanitizer->filterSyncableItems($payload['items'] ?? []);

        return $device->bookmarkSnapshots()->create([
            'payload_json' => ['items' => $items],
            'encrypted_payload' => null,
            'item_count' => count($items),
        ]);
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
     * @return EloquentCollection<int, HistoryItem>
     */
    public function storeHistoryBatch(Device $device, array $items): EloquentCollection
    {
        return DB::transaction(function () use ($device, $items): EloquentCollection {
            $historyItems = new EloquentCollection;
            $syncableItems = collect($this->urlSanitizer->filterSyncableItems($items))
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
                    $historyItems->push($historyItem);
                }
            }

            return $historyItems;
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
