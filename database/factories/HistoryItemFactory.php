<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\HistoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoryItem>
 */
class HistoryItemFactory extends Factory
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
            'url' => fake()->url(),
            'title' => fake()->sentence(3),
            'visited_at' => fake()->dateTimeBetween('-1 month'),
            'encrypted_payload' => null,
        ];
    }
}
