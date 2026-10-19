<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PendingFollow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingFollow>
 */
final class PendingFollowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'github_id' => (string) fake()->unique()->numberBetween(1_000_000, 9_999_999),
        ];
    }
}
