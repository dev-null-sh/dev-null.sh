<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProfileType;
use App\Models\Organization;
use App\Models\Profile;
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
            // The organization's profile, made from the GitHub identity given to the factory, like a handle...
            'id' => fn (array $attributes): string => Profile::factory()->create([
                'type' => ProfileType::Organization,
                'handle' => fake()->unique()->slug(2),
                'name' => fake()->company(),
                'github_synced_at' => now(),
                ...array_intersect_key($attributes, array_flip([...Profile::USER_ATTRIBUTES, 'github_id', 'created_at'])),
            ])->id,
            'preferred_lane' => null,
            'lane_color' => null,
        ];
    }

    /**
     * The GitHub identity given to the factory, like a handle or a GitHub ID, goes to the profile it makes, not to the
     * organization.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Organization
    {
        return parent::newModel(array_diff_key($attributes, array_flip([...Profile::USER_ATTRIBUTES, 'github_id'])));
    }

    /**
     * Like organizations loaded from the database, which come with their profile.
     */
    public function configure(): self
    {
        return $this->afterMaking(fn (Organization $organization): Organization => $organization->setRelation('profile', Profile::query()->findOrFail($organization->id)));
    }
}
