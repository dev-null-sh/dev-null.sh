<?php

declare(strict_types=1);

use App\Actions\CreateIssueCommentNotifications;
use App\Enums\NotificationType;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\User;

/**
 * @return array{id: int, url: string, issue: int, author_id: string|null, is_bot: bool}
 */
function issueComment(Issue $issue, ?string $authorId, int $id = 1, bool $isBot = false): array
{
    return [
        'id' => $id,
        'url' => "{$issue->url}#issuecomment-{$id}",
        'issue' => $issue->number,
        'author_id' => $authorId,
        'is_bot' => $isBot,
    ];
}

it('notifies the reporter of a comment by someone on /dev/null, as that person', function (): void {
    $reporter = User::factory()->create();
    $issue = Issue::factory()->for($reporter)->create(['number' => 1096]);
    $commenter = User::factory()->create(['github_id' => '4242']);

    resolve(CreateIssueCommentNotifications::class)->handle([issueComment($issue, '4242', 77)]);

    expect(Notification::query()->sole())
        ->recipient_id->toBe($reporter->id)
        ->actor_id->toBe($commenter->id)
        ->type->toBe(NotificationType::IssueCommented)
        ->issue_id->toBe($issue->id)
        ->url->toBe("{$issue->url}#issuecomment-77")
        ->commit_id->toBeNull();
});

it('leaves the reporter as their own actor when the commenter is not on /dev/null', function (): void {
    $issue = Issue::factory()->create();

    resolve(CreateIssueCommentNotifications::class)->handle([issueComment($issue, '999999')]);

    expect(Notification::query()->sole())
        ->recipient_id->toBe($issue->user_id)
        ->actor_id->toBe($issue->user_id);
});

it("skips the reporter's own comments, bots, and issues not opened here", function (): void {
    $reporter = User::factory()->create(['github_id' => '100']);
    $issue = Issue::factory()->for($reporter)->create(['number' => 5]);
    $elsewhere = Issue::factory()->make(['number' => 6]);

    resolve(CreateIssueCommentNotifications::class)->handle([
        issueComment($issue, '100', 1),
        issueComment($issue, '200', 2, isBot: true),
        issueComment($elsewhere, '300', 3),
    ]);

    $this->assertDatabaseCount('notifications', 0);
});

it('does nothing when every comment is from a bot', function (): void {
    $issue = Issue::factory()->create();

    resolve(CreateIssueCommentNotifications::class)->handle([issueComment($issue, '200', isBot: true)]);

    $this->assertDatabaseCount('notifications', 0);
});

it('notifies once per comment, even when it is listed again after an edit', function (): void {
    $issue = Issue::factory()->create();
    $action = resolve(CreateIssueCommentNotifications::class);

    $action->handle([issueComment($issue, '300', 1), issueComment($issue, '300', 2)]);
    $action->handle([issueComment($issue, '300', 2)]);

    $this->assertDatabaseCount('notifications', 2);
});

it('notifies of a comment whose author GitHub no longer shows, without a name', function (): void {
    $issue = Issue::factory()->create();

    resolve(CreateIssueCommentNotifications::class)->handle([issueComment($issue, null)]);

    expect(Notification::query()->sole())
        ->recipient_id->toBe($issue->user_id)
        ->actor_id->toBe($issue->user_id);
});

it('only counts issues opened in the configured repository', function (): void {
    $other = Issue::factory()->create(['repository' => 'someone/else', 'number' => 1096]);
    $here = Issue::factory()->create(['number' => 1096]);

    resolve(CreateIssueCommentNotifications::class)->handle([issueComment($here, '300')]);

    expect(Notification::query()->sole()->recipient_id)->toBe($here->user_id)
        ->and($other->user_id)->not->toBe($here->user_id);
});
