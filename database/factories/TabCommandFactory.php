<?php

namespace Database\Factories;

use App\Enums\TabCommandStatus;
use App\Models\Device;
use App\Models\TabCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabCommand>
 */
class TabCommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_device_id' => Device::factory(),
            'target_device_id' => Device::factory(),
            'url' => fake()->url(),
            'title' => fake()->sentence(3),
            'encrypted_payload' => null,
            'status' => TabCommandStatus::Pending,
        ];
    }
}
