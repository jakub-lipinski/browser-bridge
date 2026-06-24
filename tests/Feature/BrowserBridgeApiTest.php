<?php

use App\Enums\TabCommandStatus;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\NormalizedBookmark;
use App\Models\TabCommand;

beforeEach(function (): void {
    config([
        'browserbridge.api_token' => 'test-token',
        'browserbridge.max_bookmark_snapshot_payload_bytes' => 1024 * 1024,
        'browserbridge.max_bookmark_snapshot_size' => 1024 * 1024,
        'browserbridge.max_bookmark_items_per_device' => 10000,
        'browserbridge.max_tab_snapshot_payload_bytes' => 512 * 1024,
        'browserbridge.max_history_batch_size' => 500,
        'browserbridge.max_history_items_per_device' => 5000,
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
        ->assertJsonPath('data.browser', 'safari')
        ->assertJsonPath('data.platform', 'ios')
        ->assertJsonPath('data.capabilities.tab_commands', true)
        ->assertJsonPath('data.capabilities.bookmarks_read', false)
        ->assertJsonPath('data.capabilities.history_read', false)
        ->assertJsonStructure(['data' => ['uuid']]);

    $this->getJson('/api/devices?device_uuid='.$deviceUuid, browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.capabilities.tab_commands', true);
});

it('requires a registered device UUID when listing devices', function (): void {
    $this->getJson('/api/devices', browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_uuid');
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

    expect(NormalizedBookmark::query()->count())->toBe(1)
        ->and(NormalizedBookmark::query()->first()?->url)->toBe('https://laravel.com/docs');
});

it('normalizes bookmark folders and removes stale bookmarks on each snapshot', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $device->uuid,
        'items' => [
            [
                'external_id' => 'folder-1',
                'type' => 'folder',
                'title' => 'Work',
                'path' => ['Bookmarks Bar', 'Work'],
            ],
            [
                'external_id' => 'bookmark-1',
                'parent_external_id' => 'folder-1',
                'type' => 'bookmark',
                'title' => 'Laravel',
                'url' => 'https://laravel.com',
                'path' => ['Bookmarks Bar', 'Work'],
                'date_added' => now()->toIso8601String(),
            ],
            [
                'external_id' => 'bookmark-2',
                'type' => 'bookmark',
                'title' => 'Old',
                'url' => 'https://old.example.com',
                'path' => ['Bookmarks Bar'],
            ],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.item_count', 3);

    expect($device->normalizedBookmarks()->count())->toBe(3);

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $device->uuid,
        'items' => [
            [
                'external_id' => 'bookmark-1',
                'parent_external_id' => 'folder-1',
                'type' => 'bookmark',
                'title' => 'Laravel Docs',
                'url' => 'https://laravel.com/docs',
                'path' => ['Bookmarks Bar', 'Work'],
            ],
        ],
    ], browserBridgeHeaders())->assertSuccessful();

    expect($device->normalizedBookmarks()->count())->toBe(1)
        ->and($device->normalizedBookmarks()->first()?->title)->toBe('Laravel Docs');
});

it('exposes BrowserBridge bookmark snapshots across Chrome and Safari devices', function (): void {
    $chrome = Device::factory()->create([
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ]);

    $safari = Device::factory()->create([
        'name' => 'Safari Mac',
        'browser' => 'safari',
        'platform' => 'macos',
    ]);

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $chrome->uuid,
        'items' => [
            ['title' => 'BrowserBridge', 'url' => 'https://example.com/browserbridge'],
        ],
    ], browserBridgeHeaders())->assertSuccessful();

    $this->getJson('/api/bookmarks/snapshots?device_uuid='.$safari->uuid, browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.device.name', 'Chrome Mac')
        ->assertJsonPath('data.0.payload_json.items.0.url', 'https://example.com/browserbridge');
});

it('searches normalized bookmarks grouped by device data', function (): void {
    $chrome = Device::factory()->create([
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ]);

    $safari = Device::factory()->create([
        'name' => 'Safari Mac',
        'browser' => 'safari',
        'platform' => 'macos',
    ]);

    NormalizedBookmark::factory()->create([
        'device_id' => $chrome->id,
        'title' => 'BrowserBridge Docs',
        'url' => 'https://example.com/browserbridge-docs',
        'path_json' => ['Bookmarks Bar', 'BrowserBridge'],
    ]);

    NormalizedBookmark::factory()->create([
        'device_id' => $chrome->id,
        'title' => 'Unrelated',
        'url' => 'https://example.com/unrelated',
    ]);

    $this->getJson('/api/bookmarks/search?device_uuid='.$safari->uuid.'&q=browserbridge&limit=10', browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.device.name', 'Chrome Mac')
        ->assertJsonPath('data.0.path.1', 'BrowserBridge')
        ->assertJsonPath('data.0.url', 'https://example.com/browserbridge-docs');
});

it('deletes bookmarks for a device', function (): void {
    $caller = Device::factory()->create();
    $target = Device::factory()->create();

    NormalizedBookmark::factory()->count(2)->create(['device_id' => $target->id]);
    BookmarkSnapshot::factory()->count(2)->create(['device_id' => $target->id]);
    NormalizedBookmark::factory()->create(['device_id' => $caller->id]);

    $this->deleteJson('/api/bookmarks/device/'.$target->id, [
        'device_uuid' => $caller->uuid,
    ], browserBridgeHeaders())->assertNoContent();

    expect($target->normalizedBookmarks()->count())->toBe(0)
        ->and($target->bookmarkSnapshots()->count())->toBe(0)
        ->and($caller->normalizedBookmarks()->count())->toBe(1);
});

it('shows bookmark counts and searchable bookmarks on the dashboard', function (): void {
    $device = Device::factory()->create(['name' => 'Chrome Mac']);

    NormalizedBookmark::factory()->create([
        'device_id' => $device->id,
        'title' => 'BrowserBridge Dashboard Bookmark',
        'url' => 'https://example.com/dashboard-bookmark',
        'path_json' => ['Bookmarks Bar', 'BrowserBridge'],
    ]);

    $this->get('/dashboard?bookmark_query=dashboard')
        ->assertSuccessful()
        ->assertSee('BrowserBridge Bookmarks')
        ->assertSee('Chrome Mac')
        ->assertSee('BrowserBridge Dashboard Bookmark')
        ->assertSee('Bookmarks Bar / BrowserBridge')
        ->assertSee('1');
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
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.received', 2)
        ->assertJsonPath('data.stored', 1)
        ->assertJsonPath('data.skipped', 0);

    expect(HistoryItem::query()->count())->toBe(1);
});

it('rejects history upload without a valid token', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'history_sync_enabled' => true,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => now()->toIso8601String()],
        ],
    ])->assertUnauthorized();
});

it('skips invalid and internal history URLs gracefully', function (): void {
    $device = Device::factory()->create();

    $uploadHistoryUrl = fn (string $url) => $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'history_sync_enabled' => true,
        'items' => [
            ['url' => $url, 'title' => 'Private', 'visited_at' => now()->toIso8601String()],
        ],
    ], browserBridgeHeaders());

    $uploadHistoryUrl('not-a-url')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('chrome://history')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('edge://settings')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('brave://settings')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('safari-extension://abc/history.html')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('about:blank')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('file:///Users/example/private.html')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('devtools://devtools/bundled/inspector.html')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('view-source:https://example.com')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('javascript:alert(1)')->assertSuccessful()->assertJsonPath('data.skipped', 1);
    $uploadHistoryUrl('https://example.com/' . str_repeat('a', 2049))->assertSuccessful()->assertJsonPath('data.skipped', 1)->assertJsonPath('data.skipped_reasons.url_too_long', 1);
});

it('exposes BrowserBridge history across Chrome and Safari devices', function (): void {
    $chrome = Device::factory()->create([
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ]);

    $safari = Device::factory()->create([
        'name' => 'Safari Mac',
        'browser' => 'safari',
        'platform' => 'macos',
    ]);

    $this->postJson('/api/history/batch', [
        'device_uuid' => $chrome->uuid,
        'history_sync_enabled' => true,
        'items' => [
            [
                'url' => 'https://example.com/shared-history',
                'title' => 'Shared History',
                'visited_at' => now()->toIso8601String(),
            ],
        ],
    ], browserBridgeHeaders())->assertSuccessful();

    $this->getJson('/api/history/search?device_uuid='.$safari->uuid.'&query=shared&limit=10', browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.device.name', 'Chrome Mac')
        ->assertJsonPath('data.0.url', 'https://example.com/shared-history');
});

it('searches history with the q parameter', function (): void {
    $device = Device::factory()->create();

    HistoryItem::factory()->create([
        'device_id' => $device->id,
        'url' => 'https://example.com/browserbridge-history',
        'title' => 'BrowserBridge History',
        'visited_at' => now(),
    ]);

    HistoryItem::factory()->create([
        'device_id' => $device->id,
        'url' => 'https://example.com/other',
        'title' => 'Other',
        'visited_at' => now(),
    ]);

    $this->getJson('/api/history/search?device_uuid='.$device->uuid.'&q=browserbridge&limit=10', browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'BrowserBridge History');
});

it('deletes all BrowserBridge history and per-device history', function (): void {
    $chrome = Device::factory()->create();
    $safari = Device::factory()->create([
        'browser' => 'safari',
        'platform' => 'macos',
    ]);

    HistoryItem::factory()->count(2)->create(['device_id' => $chrome->id]);
    HistoryItem::factory()->count(3)->create(['device_id' => $safari->id]);

    $this->deleteJson('/api/history/device/'.$chrome->id, [
        'device_uuid' => $safari->uuid,
    ], browserBridgeHeaders())->assertNoContent();

    expect(HistoryItem::query()->count())->toBe(3);

    $this->deleteJson('/api/history', [
        'device_uuid' => $safari->uuid,
    ], browserBridgeHeaders())->assertNoContent();

    expect(HistoryItem::query()->count())->toBe(0);
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

it('limits stored history items per device', function (): void {
    config(['browserbridge.max_history_items_per_device' => 1]);

    $device = Device::factory()->create();

    HistoryItem::factory()->create(['device_id' => $device->id]);

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'history_sync_enabled' => true,
        'items' => [
            ['url' => 'https://example.com/new-history', 'title' => 'New', 'visited_at' => now()->toIso8601String()],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('sends fetches and marks tab commands as opened for the target device', function (): void {
    $source = Device::factory()->create([
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ]);
    $target = Device::factory()->create([
        'name' => 'Safari Mac',
        'browser' => 'safari',
        'platform' => 'macos',
    ]);
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

it('dismisses tab commands only for the target device', function (): void {
    $source = Device::factory()->create([
        'browser' => 'safari',
        'platform' => 'macos',
    ]);
    $target = Device::factory()->create([
        'browser' => 'chrome',
        'platform' => 'macos',
    ]);
    $other = Device::factory()->create();

    $commandId = $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $source->uuid,
        'target_device_uuid' => $target->uuid,
        'url' => 'https://example.com/from-safari',
        'title' => 'From Safari',
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->json('data.id');

    $this->postJson("/api/tabs/{$commandId}/dismissed", [
        'device_uuid' => $other->uuid,
    ], browserBridgeHeaders())->assertNotFound();

    $this->postJson("/api/tabs/{$commandId}/dismissed", [
        'device_uuid' => $target->uuid,
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.status', TabCommandStatus::Dismissed->value);

    $this->getJson('/api/tabs/incoming?device_uuid='.$target->uuid, browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('rejects tab commands for missing or same source and target devices', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $device->uuid,
        'target_device_uuid' => $device->uuid,
        'url' => 'https://example.com/same-device',
        'title' => 'Same device',
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_device_uuid');

    $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $device->uuid,
        'target_device_uuid' => fake()->uuid(),
        'url' => 'https://example.com/missing-target',
        'title' => 'Missing target',
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_device_uuid');
});

it('allows same device tab commands only when debugging is explicitly enabled', function (): void {
    config(['browserbridge.allow_same_device_tab_commands' => true]);

    $device = Device::factory()->create();

    $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $device->uuid,
        'target_device_uuid' => $device->uuid,
        'url' => 'https://example.com/debug-self-send',
        'title' => 'Debug self-send',
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.status', TabCommandStatus::Pending->value);
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

    $sendInternalUrl = fn (string $url) => $this->postJson('/api/tabs/send', [
        'source_device_uuid' => $source->uuid,
        'target_device_uuid' => $target->uuid,
        'url' => $url,
        'title' => 'Internal URL',
    ], browserBridgeHeaders());

    $sendInternalUrl('about:blank')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('safari-extension://abc/options.html')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('chrome://extensions')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('file:///Users/example/private.html')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('devtools://devtools/bundled/inspector.html')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('view-source:https://example.com')->assertUnprocessable()->assertJsonValidationErrors('url');
    $sendInternalUrl('javascript:alert(1)')->assertUnprocessable()->assertJsonValidationErrors('url');
});

it('rejects oversized bookmark and tab snapshot payloads', function (): void {
    config([
        'browserbridge.max_bookmark_snapshot_payload_bytes' => 80,
        'browserbridge.max_bookmark_snapshot_size' => 80,
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
        'status' => TabCommandStatus::Opened,
    ]);

    TabCommand::factory()->create([
        'created_at' => now()->subDays(2),
        'status' => TabCommandStatus::Dismissed,
    ]);

    TabCommand::factory()->create([
        'created_at' => now()->subDays(8),
        'status' => TabCommandStatus::Pending,
    ]);

    $this->artisan('browserbridge:cleanup')
        ->assertSuccessful();

    expect(HistoryItem::query()->count())->toBe(1)
        ->and(TabCommand::query()->count())->toBe(2);
});
