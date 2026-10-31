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
    /**
     * Held while an organization is created and while a GitHub import keeps the organizations it doesn't find pending,
     * so neither misses the other's rows. Both are short and rare, so one lock for every organization is enough.
     */
    public const string FollowsLock = 'github-organization-follows';

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
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [self::FollowsLock]);

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

                DB::table('organization_follows')->insertUsing(
                    ['user_id', 'organization_id', 'created_at'],
                    $pending->clone()->select('user_id')->selectRaw('?, ?', [$organization->id, now()]),
                );
                $pending->delete();
            }

            return $organization;
        });
    }
}
