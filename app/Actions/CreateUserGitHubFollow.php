<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GitHub;

final readonly class CreateUserGitHubFollow
{
    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * Follows the user on GitHub with the follower's own token. The account is looked up by its ID first, since a
     * username here can be stale and already claimed by someone else on GitHub. False when the account is gone.
     */
    public function handle(string $token, User $user): bool
    {
        $login = $this->github->login($user->github_id, $token);

        if ($login === null) {
            return false;
        }

        $this->github->request($token)->put("user/following/{$login}")->throw();

        return true;
    }
}
