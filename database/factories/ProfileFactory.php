<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProfileType;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profile>
 */
final class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ProfileType::User,
            'github_id' => (string) fake()->unique()->numberBetween(1, 999_999_999),
            'handle' => Str::replace(['.', '_'], '-', fake()->unique()->userName()),
            'name' => fake()->name(),
            'avatar_url' => 'https://avatars.githubusercontent.com/u/'.fake()->numberBetween(1, 999_999),
            'bio' => fake()->sentence(),
            'location' => fake()->city(),
            'website' => fake()->url(),
        ];
    }

    public function organization(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProfileType::Organization,
            'handle' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'github_synced_at' => now(),
        ]);
    }
}
