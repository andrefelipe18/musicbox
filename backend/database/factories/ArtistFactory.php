<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'youtube_music_id' => fake()->unique()->bothify('artist-####'),
            'name' => fake()->name(),
            'thumbnail_url' => fake()->optional()->imageUrl(),
        ];
    }
}
