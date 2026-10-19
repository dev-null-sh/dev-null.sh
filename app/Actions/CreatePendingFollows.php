<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class CreatePendingFollows
{
    /**
     * Remembers the GitHub accounts the user follows that aren't on /dev/null yet, so they follow them here once they
     * sign in. Accounts already here are left out.
     *
     * @param  list<string>  $githubIds
     */
    public function handle(User $user, array $githubIds): void
    {
        $githubIds = array_values(array_diff(
            $githubIds,
            User::query()->whereIn('github_id', $githubIds)->get(['github_id'])->map(fn (User $registered): string => $registered->github_id)->all(),
        ));

        if ($githubIds === []) {
            return;
        }

        $user->pendingFollows()->insertOrIgnore(array_map(
            fn (string $githubId): array => ['user_id' => $user->id, 'github_id' => $githubId],
            $githubIds,
        ));
    }
}
