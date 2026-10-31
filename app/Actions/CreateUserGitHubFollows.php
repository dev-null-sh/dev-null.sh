<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings a user's GitHub network over, once: they follow the people they follow on GitHub, and the people
 * following them on GitHub follow them here. It only ever adds follows, and never runs twice, so an unfollow sticks.
 */
final readonly class CreateUserGitHubFollows
{
    /**
     * Up to 1,000 accounts each way, so a popular account can't use up the rate limit on its own.
     */
    private const int MaxPages = 10;

    public function __construct(
        private GitHubProvider $github,
        private CreateFollowNotification $createFollowNotification,
    ) {
        //
    }

    public function handle(User $user, bool $notify = true): void
    {
        if ($user->github_follows_imported_at !== null) {
            return;
        }

        $login = $this->github->login($user->github_id);

        if ($login === null) {
            $user->update(['github_follows_imported_at' => now()]);

            return;
        }

        $following = $this->users($user, $this->github->followingIds($login, self::MaxPages));
        $followers = $this->users($user, $this->github->followerIds($login, self::MaxPages));

        DB::transaction(function () use ($user, $following, $followers, $notify): void {
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

            $user->update(['github_follows_imported_at' => now()]);
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
