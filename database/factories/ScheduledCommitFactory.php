<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduledCommit> */
final class ScheduledCommitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'scheduled_at' => now()->addDay(),
        ];
    }
}
