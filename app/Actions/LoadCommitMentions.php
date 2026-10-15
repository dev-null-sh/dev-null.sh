<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

final readonly class LoadCommitMentions
{
    /**
     * @param  iterable<Commit>  $commits
     */
    public function handle(iterable $commits): void
    {
        $handles = new Collection;
        $byCommit = [];

        foreach ($commits as $commit) {
            if ($commit->trashed()) {
                continue;
            }

            preg_match_all('/(?:^|[\s(\[{])@([a-zA-Z0-9][a-zA-Z0-9-]{0,38})(?![\p{L}\p{N}_-])/u', $commit->body, $matches);
            $names = array_unique(array_map(mb_strtolower(...), $matches[1]));
            $byCommit[] = [$commit, $names];
            $handles = $handles->merge($names);
        }

        $users = $handles->isEmpty()
            ? new Collection
            : User::query()->select('username')->whereIn(new Expression('LOWER(username)'), $handles->unique()->all())->get()
                ->mapWithKeys(fn (User $user): array => [mb_strtolower($user->username) => $user->username]);

        foreach ($byCommit as [$commit, $names]) {
            $commit->setAttribute('mentions', array_values($users->only($names)->all()));
        }
    }
}
