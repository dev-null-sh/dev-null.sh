<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings a user's GitHub network over, once: they follow the people and the organizations they follow on GitHub, and
 * the people following them on GitHub follow them here. The organizations not on /dev/null yet are kept, so the user
 * follows them once they are. It only ever adds follows, and never runs twice, so an unfollow sticks. Organizations are
 * marked as imported apart, so the users imported before organizations could be followed get them on another run.
 */
final readonly class CreateUserGitHubFollows
{
    /**
     * Up to 1,000 accounts each way, so a popular account can't use up the rate limit on its own.
     */
    public const int MaxPages = 10;

    public function __construct(
        private GitHubProvider $github,
        private CreateFollowNotification $createFollowNotification,
    ) {
        //
    }

    public function handle(User $user, bool $notify = true): void
    {
        $importsPeople = $user->github_follows_imported_at === null;
        $importsOrganizations = $user->github_organization_follows_imported_at === null;

        if (! $importsPeople && ! $importsOrganizations) {
            return;
        }

        $login = $this->github->login($user->github_id);
        $followingIds = $login === null ? ['users' => [], 'organizations' => []] : $this->github->followingIds($login, self::MaxPages);
        $following = $importsPeople ? $this->users($user, $followingIds['users']) : new Collection();
        $followers = $importsPeople && $login !== null ? $this->users($user, $this->github->followerIds($login, self::MaxPages)) : new Collection();
        $organizationIds = collect($followingIds['organizations'])->unique()->values();

        DB::transaction(function () use ($user, $notify, $following, $followers, $organizationIds, $importsPeople, $importsOrganizations): void {
            $followedIds = $user->following()->syncWithoutDetaching($following->modelKeys())['attached'];
            $followerIds = $user->followers()->syncWithoutDetaching($followers->modelKeys())['attached'];

            if ($notify) {
                foreach ($following->only($followedIds) as $followed) {
                    $this->createFollowNotification->handle($user, $followed);
                }

                foreach ($followers->only($followerIds) as $follower) {
                    $this->createFollowNotification->handle($follower, $user);
                }
            }

            if ($importsOrganizations) {
                // Waits for an organization being created from GitHub right now, which follows the pending rows it finds
                // (UpdateOrCreateOrganization), so the follow is made on one side or the other.
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [UpdateOrCreateOrganization::FollowsLock]);

                $organizations = Profile::query()->where('type', ProfileType::Organization)->whereIn('github_id', $organizationIds)->get();

                $user->followedOrganizations()->syncWithoutDetaching($organizations->modelKeys());

                DB::table('pending_organization_follows')->insertOrIgnore($organizationIds
                    ->reject(fn (string $id): bool => $organizations->contains('github_id', $id))
                    ->map(fn (string $id): array => ['user_id' => $user->id, 'github_id' => $id, 'created_at' => now()])
                    ->all());
            }

            $user->update(array_filter([
                'github_follows_imported_at' => $importsPeople ? now() : null,
                'github_organization_follows_imported_at' => $importsOrganizations ? now() : null,
            ]));
        });
    }

    /**
     * @param  list<string>  $githubIds
     * @return Collection<int, User>
     */
    private function users(User $user, array $githubIds): Collection
    {
        return User::query()
            ->whereIn('github_id', $githubIds)
            ->whereKeyNot($user->id)
            ->whereNotIn('id', $user->idsAcrossBlocks())
            ->get();
    }
}
