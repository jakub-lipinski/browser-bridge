<?php

namespace Database\Factories;

use App\Models\BookmarkBackup;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookmarkBackup>
 */
class BookmarkBackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'sync_run_id' => null,
            'payload_json' => ['items' => []],
            'encrypted_payload' => null,
            'created_at' => now(),
        ];
    }
}
