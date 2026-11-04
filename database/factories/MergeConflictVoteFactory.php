<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MergeConflictSide;
use App\Models\MergeConflict;
use App\Models\MergeConflictVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MergeConflictVote>
 */
final class MergeConflictVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merge_conflict_id' => MergeConflict::factory(),
            'user_id' => User::factory(),
            'side' => MergeConflictSide::Ours,
        ];
    }
}
