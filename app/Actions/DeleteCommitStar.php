<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\User;
use App\Services\ReactionEmoji;
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

    public function handle(User $user, Commit $commit, ?string $flavor = null): void
    {
        DB::transaction(function () use ($user, $commit, $flavor): void {
            Commit::query()->whereKey($commit->id)->lockForUpdate()->firstOrFail();
            foreach (RepositoryMirror::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get() as $mirror) {
                $reaction = DB::table('repository_reactions')->where('repository_mirror_id', $mirror->id)->where('commit_id', $commit->id);
                if ($flavor !== null) {
                    $reaction->where('emoji_key', ReactionEmoji::key($flavor));
                }
                foreach ($reaction->get() as $previous) {
                    assert(is_string($previous->flavor));
                    $this->mirrors->reaction($mirror, $user, $commit, Crypt::decryptString($previous->flavor), 'repository', removed: true);
                }
                $reaction->delete();
            }
            $public = DB::table('commit_reactions')->where('user_id', $user->id)->where('commit_id', $commit->id);
            (clone $public)->when($flavor !== null, fn ($query) => $query->where('flavor', ReactionEmoji::normalize($flavor)))->delete();
            if (! $public->exists()) {
                $user->starredCommits()->detach($commit->id);
                $this->deleteStarNotification->handle($user, $commit);
            } else {
                DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->update(['flavor' => $public->orderByDesc('updated_at')->value('flavor')]);
            }

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
