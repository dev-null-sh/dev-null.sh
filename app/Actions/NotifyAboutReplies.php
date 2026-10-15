<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Str;

final readonly class NotifyAboutReplies
{
    /**
     * Notifies the author of each reply's parent, but only once the reply is approved, so
     * nobody hears about a reply they cannot see yet. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $replyIds = array_map(
            fn (Commit $commit): string => $commit->id,
            array_filter($commits, fn (Commit $commit): bool => $commit->parent_id !== null),
        );

        if ($replyIds === []) {
            return;
        }

        $replies = Commit::query()
            ->whereKey($replyIds)
            ->where('status', CommitStatus::Approved)
            ->with('parent')
            ->get();

        $notifications = [];

        foreach ($replies as $reply) {
            $parent = $reply->parent;

            if (! $parent instanceof Commit || $parent->user_id === $reply->user_id) {
                continue;
            }

            $notifications[] = [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $parent->user_id,
                'actor_id' => $reply->user_id,
                'type' => NotificationType::Replied->value,
                'commit_id' => $reply->id,
                'key' => 'replied:'.$reply->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notification::query()->insertOrIgnore($notifications);
    }
}
