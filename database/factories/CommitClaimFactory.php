<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitClaim>
 */
final class CommitClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->pending(),
            'user_id' => User::factory(),
            'expires_at' => now()->addMinutes(CommitClaim::MINUTES),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subSecond(),
        ]);
    }
}
