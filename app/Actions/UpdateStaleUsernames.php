<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SearchIndex;
use App\Models\User;

final readonly class UpdateStaleUsernames
{
    public function __construct(private CreateSearchSyncs $createSearchSyncs)
    {
        //
    }

    /**
     * GitHub usernames are unique case-insensitively, so another user holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub username can, and their next sign-in restores a real one.
     */
    public function handle(string $username, string $githubId): void
    {
        $renamed = User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->where('github_id', '!=', $githubId)
            ->get()
            ->each(function (User $user): void {
                $user->update(['username' => '_renamed-'.$user->github_id]);
            });

        $this->createSearchSyncs->handle(SearchIndex::Users, $renamed->modelKeys());
    }
}
