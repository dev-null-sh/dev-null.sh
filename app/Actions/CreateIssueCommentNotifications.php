<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateIssueCommentNotifications
{
    /**
     * Each comment on an issue someone opened here notifies them once, keyed by the comment, so an edited comment or a
     * listing that overlaps the last one never notifies again. Their own comments and those of bots, the app included,
     * are not news to them. A commenter who is on /dev/null is the actor; anyone else, or an author GitHub no longer
     * shows, leaves the reporter as their own actor, as a level-up does, and the inbox shows the comment without a name.
     * Only issues opened in the configured repository count.
     *
     * @param  list<array{id: int, url: string, issue: int, author_id: string|null, is_bot: bool}>  $comments
     */
    public function handle(array $comments): void
    {
        $comments = array_values(array_filter($comments, fn (array $comment): bool => ! $comment['is_bot']));

        if ($comments === []) {
            return;
        }

        $issues = Issue::query()
            ->with('user:id,github_id')
            ->where('repository', config()->string('services.github.repository'))
            ->whereIn('number', array_unique(array_column($comments, 'issue')))
            ->get()
            ->keyBy('number');

        /** @var array<string, string> $actors */
        $actors = User::query()
            ->whereIn('github_id', array_values(array_unique(array_filter(array_column($comments, 'author_id')))))
            ->pluck('id', 'github_id')
            ->all();

        $rows = [];

        foreach ($comments as $comment) {
            $issue = $issues->get($comment['issue']);

            if (! $issue instanceof Issue || $comment['author_id'] === $issue->user->github_id) {
                continue;
            }

            $rows[] = [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $issue->user_id,
                'actor_id' => $actors[$comment['author_id'] ?? ''] ?? $issue->user_id,
                'type' => NotificationType::IssueCommented->value,
                'issue_id' => $issue->id,
                'url' => $comment['url'],
                'key' => 'issue_commented:'.$comment['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notification::query()->insertOrIgnore($rows);
    }
}
