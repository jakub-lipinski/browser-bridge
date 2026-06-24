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

class BrowserSyncService
{
    public function __construct(private BrowserDataSanitizer $sanitizer) {}

    /**
     * @param  array{name: string, browser: string, platform: string}  $attributes
     */
    public function registerDevice(string $uuid, array $attributes): Device
    {
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
        $items = $this->sanitizer->filterSyncableItems($payload['items'] ?? []);

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
        $tabs = $this->sanitizer->filterSyncableItems($payload['tabs'] ?? []);

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

            foreach ($this->sanitizer->filterSyncableItems($items) as $item) {
                $historyItems->push($device->historyItems()->create([
                    'url' => $item['url'],
                    'title' => $item['title'] ?? null,
                    'visited_at' => Carbon::parse($item['visited_at']),
                    'encrypted_payload' => null,
                ]));
            }

            return $historyItems;
        });
    }

    public function sendTabCommand(Device $sourceDevice, Device $targetDevice, string $url, ?string $title): TabCommand
    {
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
