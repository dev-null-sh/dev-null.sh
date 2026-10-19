<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\User;
use App\Services\RepositoryMirrors;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommitStar
{
    public function __construct(
        private DeleteStarNotification $deleteStarNotification,
        private UpdateCommitHotScores $updateCommitHotScores,
        private RepositoryMirrors $mirrors,
    ) {
        //
    }

    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            Commit::query()->whereKey($commit->id)->lockForUpdate()->firstOrFail();
            foreach (RepositoryMirror::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get() as $mirror) {
                $reaction = DB::table('repository_reactions')->where('repository_mirror_id', $mirror->id)->where('commit_id', $commit->id);
                $previous = $reaction->first();
                if ($previous !== null) {
                    assert(is_string($previous->flavor));
                    $this->mirrors->reaction($mirror, $user, $commit, Crypt::decryptString($previous->flavor), 'repository', removed: true);
                    $reaction->delete();
                }
            }
            $user->starredCommits()->detach($commit->id);

            $this->deleteStarNotification->handle($user, $commit);

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
