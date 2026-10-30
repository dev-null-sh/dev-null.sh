<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProfileType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ProfileType::Organization,
            'github_id' => (string) fake()->unique()->numberBetween(1, 999_999_999),
            'handle' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'avatar_url' => 'https://avatars.githubusercontent.com/u/'.fake()->numberBetween(1, 999_999),
            'bio' => fake()->sentence(),
            'location' => fake()->city(),
            'website' => fake()->url(),
            'github_synced_at' => now(),
        ];
    }
}
