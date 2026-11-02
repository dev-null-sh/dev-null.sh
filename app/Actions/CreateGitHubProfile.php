<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Dto\GitHubProfile;
use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class CreateGitHubProfile
{
    public function __construct(
        private GitHubProvider $github,
        private UpdateOrCreateOrganization $updateOrCreateOrganization,
        private UpdateStaleHandles $updateStaleHandles,
    ) {
        //
    }

    /**
     * Gives the handle a page from its GitHub account: an organization's, or an unclaimed developer's, which keeps only
     * what the page shows, with the members following them on GitHub waiting to follow them here. A user here who
     * renamed on GitHub moves to the handle. A handle that is no GitHub account, or the account of someone who deleted
     * theirs here, is remembered for a day, so the page says so without asking GitHub again.
     */
    public function handle(string $handle): void
    {
        $account = $this->github->accountByLogin($handle) ?? [];
        $type = Arr::get($account, 'type');

        if ($type === 'Organization') {
            $this->updateOrCreateOrganization->handle($account);

            return;
        }

        $githubId = (string) Arr::integer($account, 'id', 0);

        if ($type !== 'User' || DB::table('deleted_github_accounts')->where('github_id', $githubId)->exists()) {
            Cache::put("github-profiles:not-found:{$handle}", $type === 'User' ? 'deleted' : 'missing', now()->addDay());

            return;
        }

        $profile = GitHubProfile::fromAccount($account);

        // Asked before the profile exists, so a sign-in can't come between them and miss the members waiting...
        $followerIds = Profile::query()->where('github_id', $githubId)->exists()
            ? []
            : $this->github->followerIds($profile->handle, CreateUserGitHubFollows::MaxPages);

        DB::transaction(function () use ($profile, $githubId, $followerIds): void {
            $this->updateStaleHandles->handle($profile->handle, $githubId);

            $existing = Profile::query()->where('type', ProfileType::User)->firstWhere('github_id', $githubId);

            // A user who renamed on GitHub only moves to their new handle: their next sign-in syncs the rest...
            if ($existing?->user instanceof User) {
                $existing->update(['handle' => $profile->handle]);

                return;
            }

            $unclaimed = Profile::query()->updateOrCreate(['github_id' => $githubId, 'type' => ProfileType::User], [
                'handle' => $profile->handle,
                'name' => $profile->name,
                'avatar_url' => $profile->avatarUrl,
                'bio' => $profile->bio,
                'github_synced_at' => now(),
            ]);

            DB::table('pending_follows')->insertUsing(
                ['user_id', 'profile_id'],
                User::query()->whereIn('github_id', $followerIds)->select('id')->selectRaw('?', [$unclaimed->id]),
            );
        });
    }
}
