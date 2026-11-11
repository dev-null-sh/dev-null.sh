<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateUnclaimedProfileTopics
{
    public function __construct(private CreateCommitTopics $createCommitTopics)
    {
        //
    }

    /**
     * Links the commits that mentioned the developers before they had a page to them, by the handle each page has now,
     * as commits posted since are linked as they are posted. Only developers still not on /dev/null are linked, and a
     * mention of a handle that was a user's when it was written stays that user's. Each commit is linked as it is,
     * locked, so a commit erased in the meantime gets none. Safe to call more than once.
     */
    public function handle(Profile ...$profiles): void
    {
        /** @var array<string, string> $developers */
        $developers = collect($profiles)
            ->filter(fn (Profile $profile): bool => $profile->isUnclaimed())
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => $profile->id])
            ->all();

        if ($developers === []) {
            return;
        }

        Commit::withTrashed()
            ->where(function (Builder $query) use ($developers): void {
                foreach (array_keys($developers) as $handle) {
                    $query->orWhere('body', 'ilike', '%@'.addcslashes((string) $handle, '\\%_').'%');
                }
            })
            ->select(['id'])
            ->lazyById(500)
            ->each(function (Commit $commit) use ($developers): void {
                DB::transaction(function () use ($commit, $developers): void {
                    $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

                    if ($commit instanceof Commit) {
                        $this->createCommitTopics->handle($commit, [], array_intersect_key($developers, array_flip($commit->mentionedNonUsers())));
                    }
                });
            });
    }
}
