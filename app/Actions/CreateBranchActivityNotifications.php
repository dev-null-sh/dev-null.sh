<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Branch;
use Illuminate\Support\Str;

final readonly class CreateBranchActivityNotifications
{
    /**
     * Notifies watchers of each branch on an approved top-level commit. Never the author. Safe to call more than once.
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

        $branches = Branch::query()
            ->whereIn('name', $tagNames)
            ->with('watchers:id')
            ->get()
            ->keyBy('name');

        $notifications = [];

        foreach ($commits as $commit) {
            if ($commit->user_id === null || $commit->branch === null) {
                continue;
            }

            $branch = $branches->get($commit->branch);

            if (! $branch instanceof Branch) {
                continue;
            }

            foreach ($branch->watchers as $watcher) {
                if ($watcher->id === $commit->user_id) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $watcher->id,
                    'actor_id' => $commit->user_id,
                    'type' => NotificationType::BranchActivity->value,
                    'commit_id' => $commit->id,
                    'key' => 'branch-activity:'.$commit->id.':'.$watcher->id,
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
