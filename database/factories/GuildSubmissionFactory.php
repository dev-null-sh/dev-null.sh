<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GuildSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuildSubmission>
 */
final class GuildSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handle = fake()->unique()->lexify('guild????????');

        return [
            'user_id' => User::factory(),
            'handle' => $handle,
            'name' => $handle,
            'description' => fake()->sentence(),
            'emoji' => '💻',
        ];
    }
}
