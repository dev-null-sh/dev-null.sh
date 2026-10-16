<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitClaim
{
    /**
     * Claims the commit for the user, taking it over from whoever held it. A user reviews one commit at a time, so
     * their previous claim is released.
     */
    public function handle(User $user, Commit $commit): CommitClaim
    {
        return DB::transaction(function () use ($user, $commit): CommitClaim {
            CommitClaim::query()
                ->where('user_id', $user->id)
                ->orWhere('commit_id', $commit->id)
                ->delete();

            return CommitClaim::query()->create([
                'commit_id' => $commit->id,
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(CommitClaim::MINUTES),
            ]);
        });
    }
}
