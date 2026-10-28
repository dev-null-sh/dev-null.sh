<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Queries\SilencedActorQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateWatchNotifications
{
    /**
     * Notifies everyone watching the author of each new commit, but only once it is approved, so nobody hears about a
     * commit they cannot see yet. Replies are left out, as watching is about what someone posts, not every
     * conversation they join. Never for the people a commit mentions or quotes, who already hear about it as a mention
     * or a quote. Nobody hears from people they mute either. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $commits = Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->where('status', CommitStatus::Approved)
            ->whereNull('parent_id')
            ->whereNotNull('user_id')
            ->with('quotedCommit')
            ->get();

        if ($commits->isEmpty()) {
            return;
        }

        $silenced = new SilencedActorQuery('follows.follower_id', 'follows.followed_id');

        $watchersByAuthor = DB::table('follows')
            ->whereIn('followed_id', $commits->pluck('user_id')->unique()->values()->all())
            ->where('watching', true)
            ->whereNotExists($silenced->mutes(...))
            ->whereNotExists($silenced->blocking(...))
            ->whereNotExists($silenced->blockers(...))
            ->get(['follower_id', 'followed_id'])
            ->groupBy('followed_id');

        $notifications = [];

        foreach ($commits as $commit) {
            $alreadyNotified = [...array_values($commit->mentions ?? []), $commit->quotedCommit?->user_id];

            foreach ($watchersByAuthor->get($commit->user_id, []) as $follow) {
                if (in_array($follow->follower_id, $alreadyNotified, true)) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $follow->follower_id,
                    'actor_id' => $commit->user_id,
                    'type' => NotificationType::Committed->value,
                    'commit_id' => $commit->id,
                    'key' => 'committed:'.$commit->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Notification::query()->insertOrIgnore($notifications);
    }
}
