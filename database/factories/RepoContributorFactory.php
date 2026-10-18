<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoContributor>
 */
final class RepoContributorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repo_id' => Repo::factory(),
            'user_id' => User::factory(),
            'role' => RepoRole::Contributor,
        ];
    }

    public function maintainer(): self
    {
        return $this->state(fn (array $attributes): array => [
            'role' => RepoRole::Maintainer,
        ]);
    }
}
