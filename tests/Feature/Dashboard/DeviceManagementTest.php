<?php

use App\Enums\TabCommandStatus;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\NormalizedBookmark;
use App\Models\TabCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard loads without RouteNotFoundException', function () {
    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('device can be disconnected safely', function () {
    $device = Device::factory()->create(['is_active' => true]);
    $history = HistoryItem::factory()->create(['device_id' => $device->id]);
    $bookmark = NormalizedBookmark::factory()->create(['device_id' => $device->id]);
    $tabCommand = TabCommand::factory()->create([
        'source_device_id' => $device->id,
        'target_device_id' => $device->id,
        'status' => TabCommandStatus::Pending,
    ]);

    $response = $this->delete(route('dashboard.device.destroy', $device));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Device disconnected. Existing synced data was kept.');

    $device->refresh();
    expect($device->trashed())->toBeTrue()
        ->and($device->is_active)->toBeFalse()
        ->and($device->disconnected_at)->not->toBeNull();

    // Data should be kept
    expect(HistoryItem::count())->toBe(1)
        ->and(NormalizedBookmark::count())->toBe(1);

    // Tab command should be cancelled
    $tabCommand->refresh();
    expect($tabCommand->status)->toBe(TabCommandStatus::Dismissed);
});

test('disconnected device hidden from active list but visible in disconnected', function () {
    $active = Device::factory()->create();
    $disconnected = Device::factory()->create(['deleted_at' => now(), 'is_active' => false]);

    // Active
    $this->get(route('dashboard'))
        ->assertSee($active->name)
        ->assertDontSee($disconnected->name);

    // Disconnected
    $this->get(route('dashboard', ['status' => 'disconnected']))
        ->assertSee($disconnected->name)
        ->assertDontSee($active->name);

    // All
    $this->get(route('dashboard', ['status' => 'all']))
        ->assertSee($active->name)
        ->assertSee($disconnected->name);
});

test('purge requires exact confirmation', function () {
    $device = Device::factory()->create(['deleted_at' => now(), 'is_active' => false]);

    $response = $this->delete(route('dashboard.device.purge', $device), [
        'confirmation_text' => 'WRONG TEXT',
    ]);

    $response->assertInvalid(['confirmation_text']);
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

test('purge permanently deletes related data', function () {
    $device = Device::factory()->create(['deleted_at' => now(), 'is_active' => false]);
    HistoryItem::factory()->create(['device_id' => $device->id]);
    NormalizedBookmark::factory()->create(['device_id' => $device->id]);

    $response = $this->delete(route('dashboard.device.purge', $device), [
        'confirmation_text' => 'DELETE DEVICE DATA',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Device and related data permanently deleted.');

    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    expect(HistoryItem::count())->toBe(0)
        ->and(NormalizedBookmark::count())->toBe(0);
});

test('sending api requests to disconnected device is rejected', function () {
    config()->set('browserbridge.api_token', 'test-token');
    $device = Device::factory()->create(['deleted_at' => now(), 'is_active' => false]);

    $response = $this->withToken('test-token')->getJson('/api/devices?device_uuid='.$device->uuid);

    $response->assertStatus(403);
    $response->assertJsonFragment(['message' => 'This device was disconnected. Register again.']);
});
