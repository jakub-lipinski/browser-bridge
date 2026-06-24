<?php

namespace Database\Factories;

use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncRunStatus;
use App\Models\BookmarkSyncProfile;
use App\Models\BookmarkSyncRun;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookmarkSyncRun>
 */
class BookmarkSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => BookmarkSyncProfile::factory(),
            'source_device_id' => Device::factory(),
            'target_device_id' => Device::factory(),
            'mode' => BookmarkSyncMode::SafeFolder,
            'status' => BookmarkSyncRunStatus::Preview,
            'added_count' => 0,
            'updated_count' => 0,
            'moved_count' => 0,
            'deleted_count' => 0,
            'skipped_count' => 0,
            'duplicate_count' => 0,
            'invalid_count' => 0,
            'error_message' => null,
            'preview_json' => null,
            'result_json' => null,
        ];
    }
}
