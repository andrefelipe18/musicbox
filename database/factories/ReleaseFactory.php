<?php

namespace Database\Factories;

use App\Enums\ReleaseType;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'youtube_music_id' => fake()->unique()->bothify('release-####'),
            'title' => fake()->sentence(3),
            'type' => ReleaseType::Unknown,
        ];
    }
}
