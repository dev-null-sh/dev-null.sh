<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
final class IssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 100_000);

        return [
            'user_id' => User::factory(),
            'repository' => 'dev-null-sh/dev-null.sh',
            'number' => $number,
            'title' => fake()->sentence(),
            'url' => "https://github.com/dev-null-sh/dev-null.sh/issues/{$number}",
        ];
    }
}
