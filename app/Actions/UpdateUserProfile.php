<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Models\User;
use App\Services\GitHub;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the GitHub profile and SSH keys of a user again, without them having to sign in.
 */
final readonly class UpdateUserProfile
{
    public function __construct(
        private GitHub $github,
        private UpdateUserSshKeys $updateUserSshKeys,
        private UpdateStaleHandles $updateStaleHandles,
    ) {
        //
    }

    public function handle(User $user): bool
    {
        try {
            // Look the account up by its immutable ID: a handle can be renamed and then claimed by someone else.
            /** @var array<array-key, mixed> $account */
            $account = $this->github->request()->get("user/{$user->github_id}")->throw()->json();

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
        } catch (ConnectionException|RequestException|UniqueConstraintViolationException $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
