<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

final readonly class CreateFirstCommitNotifications
{
    /**
     * Tells the author's followers about their first commit, so it gets an answer: a newcomer's followers mostly
     * came from GitHub, and never chose to follow them here. Only once the commit is approved, and only for the
     * author's first top-level commit that was ever approved. Never from or to bots, nor to the people it mentions
     * or quotes, who already hear about it. Nobody hears from people they mute or from across a block. Safe to call
     * more than once.
     */
    public function handle(Commit ...$commits): void
    {
        // A reply is never a first commit, so replies, most of what gets committed, cost no query.
        $topLevelIds = array_values(array_map(
            fn (Commit $commit): string => $commit->id,
            array_filter($commits, fn (Commit $commit): bool => $commit->parent_id === null),
        ));

        if ($topLevelIds === []) {
            return;
        }

        $firsts = Commit::query()
            ->whereKey($topLevelIds)
            ->where('status', CommitStatus::Approved)
            ->whereNull('parent_id')
            ->whereHas('user', fn (Builder $user): Builder => $user->whereNotIn('github_id', User::BOT_GITHUB_IDS))
            // Deleted commits count too: whoever committed before isn't making their first commit.
            ->whereNotExists(fn (QueryBuilder $earlier): QueryBuilder => $earlier
                ->from('commits as earlier')
                ->whereColumn('earlier.user_id', 'commits.user_id')
                ->whereNull('earlier.parent_id')
                ->where('earlier.status', CommitStatus::Approved)
                ->whereColumn('earlier.created_at', '<', 'commits.created_at'))
            ->with(['user', 'quotedCommit'])
            ->get();

        $notifications = [];

        foreach ($firsts as $commit) {
            $author = $commit->user;
            assert($author instanceof User);

            $followerIds = $author->followers()
                ->whereNotIn('users.github_id', User::BOT_GITHUB_IDS)
                ->whereNotIn('users.id', $author->mutedBy()->newPivotQuery()->select('muter_id'))
                ->whereNotIn('users.id', $author->idsAcrossBlocks())
                ->whereNotIn('users.id', [...array_values($commit->mentions ?? []), ...array_filter([$commit->quotedCommit?->user_id])])
                ->pluck('users.id');

            foreach ($followerIds as $followerId) {
                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $followerId,
                    'actor_id' => $author->id,
                    'type' => NotificationType::FirstCommit->value,
                    'commit_id' => $commit->id,
                    'key' => 'first_commit:'.$author->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($notifications, 500) as $chunk) {
            Notification::query()->insertOrIgnore($chunk);
        }
    }
}
