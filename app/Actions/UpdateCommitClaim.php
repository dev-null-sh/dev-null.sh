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
            $commitIds = [$commit->id];

            if ($commit->series_id !== null) {
                $parts = Commit::query()->where('series_id', $commit->series_id);
                $commitIds = $parts->pluck('id')->all();
                $commit = $parts->pending()->oldest('series_position')->firstOrFail();
            }

            CommitClaim::query()
                ->where('user_id', $user->id)
                ->orWhereIn('commit_id', $commitIds)
                ->delete();

            return CommitClaim::query()->create([
                'commit_id' => $commit->id,
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(CommitClaim::MINUTES),
            ]);
        });
    }
}
