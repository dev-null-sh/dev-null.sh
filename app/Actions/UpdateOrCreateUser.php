<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Dto\GitHubSponsors;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\GitHubSponsorsQuery;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class UpdateOrCreateUser
{
    public function __construct(
        private UpdateStaleUsernames $updateStaleUsernames,
        private GitHubSponsorsQuery $gitHubSponsors,
        private UpdateSponsorships $updateSponsorships,
    ) {
        //
    }

    /**
     * Signing in is the only time the app holds the user's GitHub token, so it is when their sponsorships are read,
     * their own sponsors too while they run a sponsors org.
     */
    public function handle(GitHubUser $githubUser): User
    {
        $githubId = (string) $githubUser->getId();
        $profile = GitHubProfile::fromSocialite($githubUser);
        $email = mb_trim((string) $githubUser->getEmail());

        $existing = User::query()->where('github_id', $githubId)->first();
        $sponsors = $this->gitHubSponsors->get(
            $githubUser->token,
            $profile->username,
            withSponsors: $existing instanceof User && Conversation::sponsorsOrgOf($existing) instanceof Conversation,
        );

        $user = DB::transaction(function () use ($githubId, $profile, $email, $sponsors): User {
            $this->updateStaleUsernames->handle($profile->username, $githubId);

            return User::query()->updateOrCreate([
                'github_id' => $githubId,
            ], [
                ...$profile->attributes(),
                'email' => $email === '' ? null : $email,
                'github_synced_at' => now(),
                ...($sponsors instanceof GitHubSponsors ? ['has_sponsors_listing' => $sponsors->hasSponsorsListing] : []),
            ]);
        });

        if ($sponsors instanceof GitHubSponsors) {
            $this->updateSponsorships->handle($user, $sponsors);
        }

        return $user;
    }
}
