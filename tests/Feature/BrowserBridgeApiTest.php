<?php

use App\Enums\TabCommandStatus;
use App\Models\Device;
use App\Models\TabCommand;

beforeEach(function (): void {
    config(['browserbridge.api_token' => 'test-token']);
});

function browserBridgeHeaders(): array
{
    return ['Authorization' => 'Bearer test-token'];
}

it('requires the private API token', function (): void {
    $this->postJson('/api/device/register', [
        'device_uuid' => fake()->uuid(),
        'name' => 'Chrome Mac',
        'browser' => 'chrome',
        'platform' => 'macos',
    ])->assertUnauthorized();
});

it('registers and lists devices', function (): void {
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

    $this->getJson('/api/devices', browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.0.uuid', $deviceUuid);
});

it('stores bookmark snapshots and ignores internal browser urls', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/bookmarks/snapshot', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['title' => 'Laravel', 'url' => 'https://laravel.com/docs'],
            ['title' => 'Extensions', 'url' => 'chrome://extensions'],
            ['title' => 'Local file', 'url' => 'file:///Users/example/private.html'],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.payload_json.items.0.url', 'https://laravel.com/docs');
});

it('rejects invalid urls in snapshots', function (): void {
    $device = Device::factory()->create();

    $this->postJson('/api/tabs/snapshot', [
        'device_uuid' => $device->uuid,
        'tabs' => [
            ['title' => 'Broken', 'url' => 'not-a-url'],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tabs.0.url');
});

it('limits history batch size and stores only syncable history items', function (): void {
    config(['browserbridge.max_history_batch_size' => 2]);

    $device = Device::factory()->create();

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => now()->toIso8601String()],
            ['url' => 'https://example.com/b', 'title' => 'B', 'visited_at' => now()->toIso8601String()],
            ['url' => 'https://example.com/c', 'title' => 'C', 'visited_at' => now()->toIso8601String()],
        ],
    ], browserBridgeHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $this->postJson('/api/history/batch', [
        'device_uuid' => $device->uuid,
        'items' => [
            ['url' => 'https://example.com/a', 'title' => 'A', 'visited_at' => now()->toIso8601String()],
            ['url' => 'about:blank', 'title' => 'Blank', 'visited_at' => now()->toIso8601String()],
        ],
    ], browserBridgeHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', 'https://example.com/a');
});

it('scopes incoming tab commands to the target device', function (): void {
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

it('rejects internal urls for send tab commands', function (): void {
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
