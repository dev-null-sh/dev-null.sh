<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MergeConflictSide;
use App\Models\MergeConflict;
use App\Models\MergeConflictVote;
use App\Models\User;

final readonly class CreateMergeConflictVote
{
    /**
     * Like a poll vote, picking a side is final: a repeated or concurrent second pick records nothing.
     */
    public function handle(User $user, MergeConflict $mergeConflict, MergeConflictSide $side): bool
    {
        return MergeConflictVote::query()->insertOrIgnore([
            'merge_conflict_id' => $mergeConflict->id,
            'user_id' => $user->id,
            'side' => $side->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
