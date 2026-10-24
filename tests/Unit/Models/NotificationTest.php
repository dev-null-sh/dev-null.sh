<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Issue;
use App\Models\Notification;

test('to array', function (): void {
    $notification = Notification::factory()->create()->refresh();

    expect(array_keys($notification->toArray()))
        ->toBe([
            'id',
            'recipient_id',
            'actor_id',
            'type',
            'commit_id',
            'read_at',
            'created_at',
            'updated_at',
            'reason',
            'subject_id',
            'level',
            'issue_id',
            'url',
            'rank',
            'rank_description',
        ]);
});

it('belongs to a recipient and an actor', function (): void {
    $notification = Notification::factory()->create();

    expect($notification->recipient->id)->toBe($notification->recipient_id)
        ->and($notification->actor->id)->toBe($notification->actor_id)
        ->and($notification->commit)->toBeNull();
});

it('may point to a reply', function (): void {
    $notification = Notification::factory()->replied()->create();

    expect($notification->type)->toBe(NotificationType::Replied)
        ->and($notification->commit->user_id)->toBe($notification->actor_id)
        ->and($notification->commit->parent->user_id)->toBe($notification->recipient_id);
});

it('may point to a comment on an issue the recipient opened', function (): void {
    $issue = Issue::factory()->create();
    $notification = Notification::factory()->issueCommented($issue, 7)->create();

    expect($notification->type)->toBe(NotificationType::IssueCommented)
        ->and($notification->issue->id)->toBe($issue->id)
        ->and($notification->recipient_id)->toBe($issue->user_id)
        ->and($notification->url)->toBe("{$issue->url}#issuecomment-7")
        ->and($notification->commit)->toBeNull();
});

it('may point to a starred commit', function (): void {
    $notification = Notification::factory()->starred()->create();

    expect($notification->type)->toBe(NotificationType::Starred)
        ->and($notification->commit->user_id)->toBe($notification->recipient_id)
        ->and($notification->actor->hasStarred($notification->commit))->toBeTrue();
});
