<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Dto\GitHubProfile;
use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class UpdateOrCreateUser
{
    public function __construct(private GitHubProvider $github, private UpdateStaleHandles $updateStaleHandles)
    {
        //
    }

    /**
     * Signing in makes the GitHub account's profile a user's: an unclaimed one, looked up from GitHub before, becomes
     * theirs with its ID. Someone who deleted their account and comes back is no longer remembered as gone.
     */
    public function handle(GitHubUser $githubUser): User
    {
        $githubId = (string) $githubUser->getId();
        $profile = GitHubProfile::fromSocialite($githubUser);
        $email = mb_trim((string) $githubUser->getEmail());

        // Null when GitHub can't answer, which keeps the last known value.
        $hasSponsorsListing = $this->github->hasSponsorsListing($githubUser->token);

        return DB::transaction(function () use ($githubId, $profile, $email, $hasSponsorsListing): User {
            $this->updateStaleHandles->handle($profile->handle, $githubId);

            // A profile never changes type, so an organization's GitHub ID would fail here, but GitHub never signs one in...
            $id = Profile::query()->updateOrCreate([
                'github_id' => $githubId,
                'type' => ProfileType::User,
            ], [
                ...$profile->attributes(),
                'github_synced_at' => now(),
            ])->id;

            DB::table('deleted_github_accounts')->where('github_id', $githubId)->delete();

            return User::query()->updateOrCreate([
                'id' => $id,
                'github_id' => $githubId,
            ], [
                'company' => $profile->company,
                'email' => $email === '' ? null : $email,
                ...($hasSponsorsListing === null ? [] : ['has_sponsors_listing' => $hasSponsorsListing]),
            ]);
        });
    }
}
