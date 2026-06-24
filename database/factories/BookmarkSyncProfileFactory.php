<?php

namespace Database\Factories;

use App\Enums\BookmarkSyncDirection;
use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncTargetScope;
use App\Models\BookmarkSyncProfile;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookmarkSyncProfile>
 */
class BookmarkSyncProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'BrowserBridge import',
            'source_device_id' => Device::factory(),
            'target_device_id' => Device::factory(),
            'mode' => BookmarkSyncMode::SafeFolder,
            'direction' => BookmarkSyncDirection::SourceToTarget,
            'target_scope' => BookmarkSyncTargetScope::BrowserBridgeFolder,
            'selected_target_folder_id' => null,
            'auto_sync_enabled' => false,
            'auto_sync_interval_minutes' => null,
            'last_run_at' => null,
            'next_run_at' => null,
            'is_active' => true,
        ];
    }
}
