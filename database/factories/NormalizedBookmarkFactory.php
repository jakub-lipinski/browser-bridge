<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\NormalizedBookmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NormalizedBookmark>
 */
class NormalizedBookmarkFactory extends Factory
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
            'external_id' => fake()->uuid(),
            'parent_external_id' => null,
            'type' => 'bookmark',
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'path_json' => ['Bookmarks Bar'],
            'date_added' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
