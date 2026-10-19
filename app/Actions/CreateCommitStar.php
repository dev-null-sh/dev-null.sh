<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\User;
use App\Services\RepositoryMirrors;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateCommitStar
{
    public function __construct(
        private CreateStarNotification $createStarNotification,
        private DeleteStarNotification $deleteStarNotification,
        private UpdateCommitHotScores $updateCommitHotScores,
        private RepositoryMirrors $mirrors,
    ) {}

    /** @param list<string> $repositoryIds */
    public function handle(User $user, Commit $commit, ?string $flavor = null, bool $public = true, array $repositoryIds = []): void
    {
        DB::transaction(function () use ($user, $commit, $flavor, $public, $repositoryIds): void {
            // Serialize first inserts as well as updates; a repeated click cannot create duplicate mirror events.
            Commit::query()->whereKey($commit->id)->lockForUpdate()->firstOrFail();
            $mirrors = RepositoryMirror::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get();
            if ((! $public && $repositoryIds === []) || array_diff($repositoryIds, $mirrors->modelKeys()) !== []) {
                throw ValidationException::withMessages(['repositories' => 'Choose Public or one of your connected repositories.']);
            }
            $previousPublic = DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->exists();

            if ($public) {
                $user->starredCommits()->newPivotStatement()->upsert([
                    'user_id' => $user->id, 'commit_id' => $commit->id, 'flavor' => $flavor,
                    'created_at' => now(), 'updated_at' => now(),
                ], ['user_id', 'commit_id'], ['flavor', 'updated_at']);
                $this->createStarNotification->handle($user, $commit);
            } else {
                $user->starredCommits()->detach($commit->id);
                $this->deleteStarNotification->handle($user, $commit);
            }

            foreach ($mirrors as $mirror) {
                $privateQuery = DB::table('repository_reactions')->where('repository_mirror_id', $mirror->id)->where('commit_id', $commit->id);
                $previous = $privateQuery->first();
                if ($previous !== null) {
                    assert(is_string($previous->flavor));
                }
                if (in_array($mirror->id, $repositoryIds, true)) {
                    $emoji = $flavor ?? 'star';
                    if ($previous === null || Crypt::decryptString($previous->flavor) !== $emoji || $previousPublic !== $public) {
                        $this->mirrors->reaction($mirror, $user, $commit, $flavor, $public ? 'public' : 'repository');
                        $privateQuery->updateOrInsert(['repository_mirror_id' => $mirror->id, 'commit_id' => $commit->id], [
                            'user_id' => $user->id, 'flavor' => Crypt::encryptString($emoji), 'created_at' => $previous->created_at ?? now(), 'updated_at' => now(),
                        ]);
                    }
                } elseif ($previous !== null) {
                    $this->mirrors->reaction($mirror, $user, $commit, Crypt::decryptString($previous->flavor), 'repository', removed: true);
                    $privateQuery->delete();
                }
            }

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
