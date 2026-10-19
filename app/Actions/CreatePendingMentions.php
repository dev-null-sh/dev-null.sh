<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Services\Mentions;

final readonly class CreatePendingMentions
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * Keeps the mentions of developers who aren't on /dev/null yet, which give them a profile to claim.
     */
    public function handle(Commit $commit): void
    {
        $usernames = array_diff($this->mentions->usernames($commit->body), array_keys($commit->mentions ?? []));

        if ($usernames === []) {
            return;
        }

        $commit->pendingMentions()->insertOrIgnore(array_map(
            fn (string $username): array => ['commit_id' => $commit->id, 'username' => $username],
            array_values($usernames),
        ));
    }
}
