<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->words(2, true),
            'browser' => fake()->randomElement(['chrome', 'safari']),
            'platform' => fake()->randomElement(['macos', 'windows', 'ios']),
            'capabilities_json' => null,
            'last_seen_at' => now(),
        ];
    }
}
