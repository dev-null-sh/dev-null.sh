<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guild>
 */
final class GuildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'handle' => fake()->unique()->lexify('guild????????'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'emoji' => '💻',
        ];
    }
}
