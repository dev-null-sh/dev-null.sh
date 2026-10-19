<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Tag;
use Illuminate\Support\Str;

final readonly class CreateTagActivityNotifications
{
    /**
     * Notifies watchers of each hashtag on an approved top-level commit. Never the author. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $commits = Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->where('status', CommitStatus::Approved)
            ->whereNull('parent_id')
            ->get();

        if ($commits->isEmpty()) {
            return;
        }

        $tagNames = $commits
            ->pluck('branch')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tagNames === []) {
            return;
        }

        $tags = Tag::query()
            ->whereIn('name', $tagNames)
            ->with('watchers:id')
            ->get()
            ->keyBy('name');

        $notifications = [];

        foreach ($commits as $commit) {
            if ($commit->user_id === null || $commit->branch === null) {
                continue;
            }

            $tag = $tags->get($commit->branch);

            if (! $tag instanceof Tag) {
                continue;
            }

            foreach ($tag->watchers as $watcher) {
                if ($watcher->id === $commit->user_id) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $watcher->id,
                    'actor_id' => $commit->user_id,
                    'type' => NotificationType::TagActivity->value,
                    'commit_id' => $commit->id,
                    'key' => 'tag-activity:'.$commit->id.':'.$watcher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($notifications !== []) {
            Notification::query()->insertOrIgnore($notifications);
        }
    }
}
