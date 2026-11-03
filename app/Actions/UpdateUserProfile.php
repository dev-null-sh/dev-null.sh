<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Dto\GitHubProfile;
use App\Jobs\LookUpUserOrganizations;
use App\Models\User;
use App\Queries\GitHubReadmeQuery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the GitHub profile, profile README and SSH keys of a user again, without them having to sign in, and looks
 * the organizations their bio and company mention up.
 */
final readonly class UpdateUserProfile
{
    public function __construct(
        private GitHubProvider $github,
        private UpdateUserSshKeys $updateUserSshKeys,
        private UpdateStaleHandles $updateStaleHandles,
        private GitHubReadmeQuery $readme,
    ) {
        //
    }

    public function handle(User $user): bool
    {
        try {
            $account = $this->github->account($user->github_id);

            if ($account === null) {
                return false;
            }

            $this->updateUserSshKeys->handle($user);

            $profile = GitHubProfile::fromAccount($account);

            DB::transaction(function () use ($user, $profile): void {
                $this->updateStaleHandles->handle($profile->handle, $user->github_id);

                $user->profile->update([
                    ...$profile->attributes(),
                    'github_synced_at' => now(),
                ]);

                // The email is left alone: the public API only has the public one, sign-in also knows the private one.
                $user->update(['company' => $profile->company]);
            });

            LookUpUserOrganizations::dispatchFor($user);

            $this->readme->forget($user);
        } catch (HttpClientException|UniqueConstraintViolationException $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
