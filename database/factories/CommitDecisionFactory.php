<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitDecision>
 */
final class CommitDecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'user_id' => User::factory(),
            'status' => CommitStatus::Approved,
            'reason' => null,
        ];
    }
}
