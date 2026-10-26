<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationReason;
use App\Enums\RemotesFrom;
use App\Models\Ban;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_id' => (string) fake()->unique()->numberBetween(1, 999_999_999),
            // Not a column: the user's profile, made from the GitHub identity given to the factory, like a handle...
            'profile' => fn (array $attributes): Profile => Profile::query()->firstWhere('github_id', $attributes['github_id'])
                ?? Profile::factory()->create([
                    ...array_intersect_key($attributes, array_flip(Profile::USER_ATTRIBUTES)),
                    'github_id' => $attributes['github_id'],
                ]),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->company(),
            'has_sponsors_listing' => false,
            'preferred_lane' => null,
            'timezone' => null,
            'lane_color' => null,
            'pinned_commit_id' => null,
            'onboarded_at' => now(),
            'commit_rules_accepted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * The GitHub identity given to the factory, like a handle, goes to the profile it makes, not to the user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): User
    {
        return parent::newModel(array_diff_key($attributes, array_flip([...Profile::USER_ATTRIBUTES, 'profile'])));
    }

    /**
     * Like users loaded from the database, which come with their profile.
     */
    public function configure(): self
    {
        return $this->afterMaking(fn (User $user): User => $user->setRelation('profile', Profile::query()->where('github_id', $user->github_id)->firstOrFail()));
    }

    /**
     * Someone who never posted, and so hasn't read the commit rules yet.
     */
    public function firstTimePoster(): self
    {
        return $this->state(fn (array $attributes): array => [
            'commit_rules_accepted_at' => null,
        ]);
    }

    /**
     * Banned the way moderators ban: by the GitHub account, and on the user.
     */
    public function banned(ModerationReason $reason = ModerationReason::Spam): self
    {
        return $this->state(fn (array $attributes): array => [
            'banned_at' => now(),
        ])->afterCreating(function (User $user) use ($reason): void {
            Ban::factory()->create(['github_id' => $user->github_id, 'reason' => $reason]);
        });
    }

    public function notOnboarded(): self
    {
        return $this->state(fn (array $attributes): array => [
            'onboarded_at' => null,
        ]);
    }

    public function withBlankProfile(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
            'avatar_url' => null,
            'bio' => null,
            'location' => null,
            'website' => null,
            'company' => null,
        ]);
    }

    /**
     * Remotes turned on. Everyone may push by default here, so tests opt into requests on purpose.
     */
    public function withRemotes(RemotesFrom $from = RemotesFrom::Everyone): self
    {
        return $this->state(fn (): array => [
            'remotes_enabled' => true,
            'remotes_from' => $from,
        ]);
    }
}
