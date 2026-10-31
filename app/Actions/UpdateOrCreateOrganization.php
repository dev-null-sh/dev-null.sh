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
     * `GET /users/{username}`. Another profile holding its handle renamed away on GitHub.
     *
     * @param  array<array-key, mixed>  $account
     */
    public function handle(array $account): Organization
    {
        $profile = GitHubProfile::fromAccount($account);
        $githubId = Arr::get($account, 'id');
        assert(is_int($githubId));

        return DB::transaction(function () use ($profile, $githubId): Organization {
            $this->updateStaleHandles->handle($profile->handle, (string) $githubId);

            // A profile never changes type: a user's account GitHub has since turned into an organization fails on its
            // GitHub ID, until that case is handled...
            $organization = Profile::query()->updateOrCreate(['github_id' => (string) $githubId, 'type' => ProfileType::Organization], [
                ...$profile->attributes(),
                'github_synced_at' => now(),
            ]);

            return Organization::query()->firstOrCreate(['id' => $organization->id]);
        });
    }
}
