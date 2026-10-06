<?php

namespace Database\Factories;

use App\Enums\UserReleaseStatus;
use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRelease>
 */
class UserReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'release_id' => Release::factory(),
            'status' => UserReleaseStatus::WantToListen,
        ];
    }
}
