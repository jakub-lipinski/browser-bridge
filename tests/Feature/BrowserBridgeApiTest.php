<?php

use App\Enums\TabCommandStatus;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\TabCommand;

beforeEach(function (): void {
    config([
        'browserbridge.api_token' => 'test-token',
        'browserbridge.max_bookmark_snapshot_payload_bytes' => 1024 * 1024,
        'browserbridge.max_tab_snapshot_payload_bytes' => 512 * 1024,
        'browserbridge.max_history_batch_size' => 500,
        'browserbridge.max_pending_tab_commands_per_target' => 100,
    ]);
});

function browserBridgeHeaders(): array
{
    return ['Authorization' => 'Bearer test-token'];
}

it('rejects missing invalid and non bearer API tokens', function (): void {
    $payload = [
        'device_uuid' => fake()->uuid(),
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ];

    $this->postJson('/api/device/register', $payload)->assertUnauthorized();

    $this->postJson('/api/device/register', $payload, [
        'Authorization' => 'Bearer wrong-token',
    ])->assertUnauthorized();

    $this->postJson('/api/device/register', $payload, [
        'X-BrowserBridge-Token' => 'test-token',
    ])->assertUnauthorized();
});

it('registers devices with provided or generated UUIDs', function (): void {
    $deviceUuid = fake()->uuid();

    $this->postJson('/api/device/register', [
        'device_uuid' => $deviceUuid,
        'name' => 'Work Chrome',
        'browser' => 'chrome',
        'platform' => 'macos',
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.uuid', $deviceUuid)
        ->assertJsonPath('data.name', 'Work Chrome');

    $this->postJson('/api/device/register', [
        'name' => 'Safari iPhone',
        'browser' => 'safari',
        'platform' => 'ios',
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Safari iPhone')
        ->assertJsonStructure(['data' => ['uuid']]);

    $this->getJson('/api/devices', browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('enforces the registered device limit', function (): void {
    config(['browserbridge.max_devices' => 1]);

    Device::factory()->create();

    $this->postJson('/api/device/register', [
        'device_uuid' => fake()->uuid(),
        'name' => 'Extra Chrome',
        'browser' => 'chrome',
        'platform' => 'macos',
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_uuid');
});

it('uploads bookmark snapshots and filters internal browser URLs', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['title' => 'Laravel', 'url' => 'https://laravel.com/docs'],
            ['title' => 'Extensions', 'url' => 'chrome://extensions'],
            ['title' => 'Brave', 'url' => 'brave://settings'],
            ['title' => 'Source', 'url' => 'view-source:https://example.com'],
            ['title' => 'Local file', 'url' => 'file:///Users/example/private.html'],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.payload_json.items.0.url', 'https://laravel.com/docs');
});

it('uploads tab snapshots and rejects invalid URLs', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/tabs/snapshot', [
        'device_uuid' => $device->uuid,
        'tabs' => [
            ['title' => 'BrowserBridge', 'url' => 'https://example.com/browserbridge', 'active' => true],
            ['title' => 'DevTools', 'url' => 'devtools://devtools/bundled/inspector.html'],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.tab_count', 1);

    $this->postJson('/api/tabs/snapshot', [
        'device_uuid' => $device->uuid,
        'tabs' => [
            ['title' => 'Broken', 'url' => 'not-a-url'],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tabs.0.url');
});

it('requires history opt in and deduplicates history items', function (): void {
    $device = Device::factory()->create();
    $visitedAt = now()->toIso8601String();

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => $visitedAt],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('history_sync_enabled');

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'history_sync_enabled' => true,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => $visitedAt],
            ['url' => 'https://example.com/a', 'title' => 'A again', 'visited_at' => $visitedAt],
            ['url' => 'javascript:alert(1)', 'title' => 'Script', 'visited_at' => $visitedAt],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', 'https://example.com/a');

    expect(HistoryItem::query()->count())->toBe(1);
});

it('limits history batch size', function (): void {
    config(['browserbridge.max_history_batch_size' => 1]);

    $device = Device::factory()->create();

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'history_sync_enabled' => true,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => now()->toIso8601String()],
            ['url' => 'https://example.com/b', 'title' => 'B', 'visited_at' => now()->toIso8601String()],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('sends fetches and marks tab commands as opened for the target device', function (): void {
    $source = Device::factory()->create();
    $target = Device::factory()->create();
    $other = Device::factory()->create();

    $commandId = $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $source->uuid,
        'target_device_uuid' => $target->uuid,
        'url' => 'https://example.com/read-this',
        'title' => 'Read this',
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.status', TabCommandStatus::Pending->value)
        ->json('data.id');

    $this->getJson('/api/tabs/incoming?device_uuid='.$target->uuid, browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $commandId);

    $this->getJson('/api/tabs/incoming?device_uuid='.$other->uuid, browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');

    $this->postJson("/api/tabs/{$commandId}/opened", [
        'device_uuid' => $other->uuid,
    ], browserBridgeHeaders())->assertNotFound();

    $this->postJson("/api/tabs/{$commandId}/opened", [
        'device_uuid' => $target->uuid,
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.status', TabCommandStatus::Opened->value);

    expect(TabCommand::findOrFail($commandId)->status)->toBe(TabCommandStatus::Opened);
});

it('limits pending tab commands per target device', function (): void {
    config(['browserbridge.max_pending_tab_commands_per_target' => 1]);

    $source = Device::factory()->create();
    $target = Device::factory()->create();

    TabCommand::factory()->create([
        'source_device_id' => $source->id,
        'target_device_id' => $target->id,
        'status' => TabCommandStatus::Pending,
    ]);

    $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $source->uuid,
        'target_device_uuid' => $target->uuid,
        'url' => 'https://example.com/too-many',
        'title' => 'Too many',
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_device_uuid');
});

it('rejects internal URLs for send tab commands', function (): void {
    $source = Device::factory()->create();
    $target = Device::factory()->create();

    $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $source->uuid,
        'target_device_uuid' => $target->uuid,
        'url' => 'about:blank',
        'title' => 'Blank',
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
});

it('rejects oversized bookmark and tab snapshot payloads', function (): void {
    config([
        'browserbridge.max_bookmark_snapshot_payload_bytes' => 80,
        'browserbridge.max_tab_snapshot_payload_bytes' => 80,
    ]);

    $device = Device::factory()->create();
    $largeTitle = str_repeat('A', 120);

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['title' => $largeTitle, 'url' => 'https://example.com/bookmark'],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $this->postJson('/api/tabs/snapshot', [
        'device_uuid' => $device->uuid,
        'tabs' => [
            ['title' => $largeTitle, 'url' => 'https://example.com/tab'],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tabs');
});

it('cleans up expired history items and tab commands', function (): void {
    $oldDevice = Device::factory()->create();
    $newDevice = Device::factory()->create();

    HistoryItem::factory()->create([
        'device_id' => $oldDevice->id,
        'visited_at' => now()->subDays(15),
    ]);

    HistoryItem::factory()->create([
        'device_id' => $newDevice->id,
        'visited_at' => now()->subDays(3),
    ]);

    TabCommand::factory()->create([
        'created_at' => now()->subDays(8),
    ]);

    TabCommand::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('browserbridge:cleanup')
        ->assertSuccessful();

    expect(HistoryItem::query()->count())->toBe(1)
        ->and(TabCommand::query()->count())->toBe(1);
});
