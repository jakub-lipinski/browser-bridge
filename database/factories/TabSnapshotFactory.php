<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\TabSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabSnapshot>
 */
class TabSnapshotFactory extends Factory
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
            'payload_json' => [
                ['title' => fake()->sentence(3), 'url' => fake()->url()],
            ],
            'encrypted_payload' => null,
            'tab_count' => 1,
        ];
    }
}
