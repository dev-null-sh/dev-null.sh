<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Enums\ProfileType;
use App\Models\Organization;
use App\Models\Profile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrCreateOrganization
{
    public function __construct(private UpdateStaleHandles $updateStaleHandles)
    {
        //
    }

    /**
     * Mirrors a GitHub organization on its profile, from an account of the GitHub REST API, such as
     * `GET /users/{username}`. Another profile holding its handle renamed away on GitHub. A new organization is
     * followed by the users following it on GitHub whose follows were imported before it showed up.
     *
     * @param  array<array-key, mixed>  $account
     */
    public function handle(array $account): Organization
    {
        $profile = GitHubProfile::fromAccount($account);
        $githubId = Arr::get($account, 'id');
        assert(is_int($githubId));

        return DB::transaction(function () use ($profile, $githubId): Organization {
            // Waits for the GitHub follow imports keeping this organization pending right now (CreateUserGitHubFollows).
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ["github-organization:{$githubId}"]);

            $this->updateStaleHandles->handle($profile->handle, (string) $githubId);

            // A profile never changes type: a user's account GitHub has since turned into an organization fails on its
            // GitHub ID, until that case is handled...
            $organizationProfile = Profile::query()->updateOrCreate(['github_id' => (string) $githubId, 'type' => ProfileType::Organization], [
                ...$profile->attributes(),
                'github_synced_at' => now(),
            ]);

            $organization = Organization::query()->firstOrCreate(['id' => $organizationProfile->id]);

            if ($organization->wasRecentlyCreated) {
                $pending = DB::table('pending_organization_follows')->where('github_id', (string) $githubId);

                $organization->followers()->attach($pending->pluck('user_id')->all());
                $pending->delete();
            }

            return $organization;
        });
    }
}
