<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Models\User;
use App\Services\GitHub;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes the mirrored profile of a user who stays signed in, so changes made on GitHub show up without signing in again.
 */
final readonly class UpdateUserProfileFromGitHub
{
    public function __construct(private GitHub $github, private UpdateUserProfile $updateUserProfile)
    {
        //
    }

    public function handle(User $user): void
    {
        try {
            $account = $this->github->account($user->github_id);
        } catch (ConnectionException|RequestException $exception) {
            $this->skip($user, $exception->getMessage());

            return;
        }

        if ($account === null) {
            $this->skip($user, 'The GitHub account no longer exists.');

            return;
        }

        $this->updateUserProfile->handle($user, GitHubProfile::fromAccount($account));
    }

    /**
     * Keeps the last known profile, and marks it as synced anyway so a broken account is retried tomorrow rather than on every request.
     */
    private function skip(User $user, string $reason): void
    {
        Log::warning('Could not refresh the GitHub profile.', [
            'username' => $user->username,
            'reason' => $reason,
        ]);

        $user->update(['github_synced_at' => now()]);
    }
}
