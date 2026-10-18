<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Talk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Talk>
 */
final class TalkFactory extends Factory
{
    protected $model = Talk::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'presented_on' => fake()->date(),
            'slides_url' => 'https://example.com/'.fake()->slug(),
            'recording_url' => null,
            'event_name' => fake()->optional()->company(),
        ];
    }
}
