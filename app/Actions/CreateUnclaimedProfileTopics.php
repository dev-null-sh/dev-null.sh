<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProfileType;
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
     * as commits posted since are linked as they are posted. Only developers still not on /dev/null are linked. Safe to
     * call more than once.
     */
    public function handle(Profile ...$profiles): void
    {
        $developers = $this->developers($profiles);

        if ($developers === []) {
            return;
        }

        $this->link(Commit::withTrashed()
            ->where(function (Builder $query) use ($developers): void {
                foreach (array_keys($developers) as $handle) {
                    $query->orWhere('body', 'ilike', '%@'.addcslashes((string) $handle, '\\%_').'%');
                }
            })
            ->select(['id', 'body', 'mentions'])
            ->lazyById(500), $developers);
    }

    /**
     * The given developers, or every one, who have a page but aren't on /dev/null yet, by lowercase handle.
     *
     * @param  iterable<array-key, Profile>|null  $profiles
     * @return array<string, string>
     */
    public function developers(?iterable $profiles = null): array
    {
        $developers = [];

        foreach ($profiles ?? Profile::query()->where('type', ProfileType::User)->whereDoesntHave('user')->lazyById(500) as $profile) {
            // The query already left out the claimed ones...
            if ($profiles === null || $profile->isUnclaimed()) {
                $developers[Str::lower($profile->handle)] = $profile->id;
            }
        }

        return $developers;
    }

    /**
     * Links each of the commits to the developers it mentions among the given ones. A mention of a handle that was a
     * user's when it was written stays that user's. Each commit is linked as it is, locked, so a commit erased in the
     * meantime gets none. Returns how many commits were linked.
     *
     * @param  iterable<int, Commit>  $commits
     * @param  array<string, string>  $developers
     */
    public function link(iterable $commits, array $developers): int
    {
        $linked = 0;

        foreach ($commits as $commit) {
            if ($this->mentioned($commit, $developers) === []) {
                continue;
            }

            $linked += DB::transaction(function () use ($commit, $developers): int {
                $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);
                $mentioned = $commit instanceof Commit ? $this->mentioned($commit, $developers) : [];

                if (! $commit instanceof Commit || $mentioned === []) {
                    return 0;
                }

                $this->createCommitTopics->handle($commit, [], $mentioned);

                return 1;
            });
        }

        return $linked;
    }

    /**
     * @param  array<string, string>  $developers
     * @return array<string, string>
     */
    private function mentioned(Commit $commit, array $developers): array
    {
        return array_intersect_key($developers, array_flip($commit->mentionedNonUsers()));
    }
}
