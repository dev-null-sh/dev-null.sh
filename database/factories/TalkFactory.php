<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TalkLinkKind;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Talk> */
final class TalkFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'presented_on' => fake()->date(),
            'link_url' => fake()->url(),
            'link_kind' => TalkLinkKind::Slides,
            'event_name' => fake()->optional()->company(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now(),
        ]);
    }
}
