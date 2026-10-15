<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class NotifyAboutMentions
{
    public function __construct(private FindMentions $findMentions)
    {
        //
    }

    /**
     * Notifies everyone mentioned in each commit, but only once the commit is approved, so nobody hears about a
     * mention they cannot see yet. Never for mentioning yourself, and never the parent's author of a reply, who
     * already hears about it as a reply. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $commits = Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->where('status', CommitStatus::Approved)
            ->with('parent')
            ->get();

        $usernamesByCommit = $commits->mapWithKeys(fn (Commit $commit): array => [
            $commit->id => $this->findMentions->handle($commit->body),
        ])->filter();

        if ($usernamesByCommit->isEmpty()) {
            return;
        }

        $users = User::query()
            ->whereIn(DB::raw('LOWER(username)'), $usernamesByCommit->flatten()->unique()->values()->all())
            ->get(['id', 'username'])
            ->keyBy(fn (User $user): string => Str::lower($user->username));

        $notifications = [];

        foreach ($commits as $commit) {
            foreach ($usernamesByCommit->get($commit->id, []) as $username) {
                $user = $users->get($username);

                if (! $user instanceof User || $user->id === $commit->user_id || $user->id === $commit->parent?->user_id) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $user->id,
                    'actor_id' => $commit->user_id,
                    'type' => NotificationType::Mentioned->value,
                    'commit_id' => $commit->id,
                    'key' => 'mentioned:'.$commit->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Notification::query()->insertOrIgnore($notifications);
    }
}
