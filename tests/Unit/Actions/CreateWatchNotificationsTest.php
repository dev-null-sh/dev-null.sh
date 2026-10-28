<?php

declare(strict_types=1);

use App\Actions\CreateWatchNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('notifies everyone watching the author of an approved commit', function (): void {
    $author = User::factory()->create();
    $watcher = User::factory()->create();
    $follower = User::factory()->create();
    $watcher->following()->attach($author, ['watching' => true]);
    $follower->following()->attach($author);
    $commit = Commit::factory()->for($author)->create();

    resolve(CreateWatchNotifications::class)->handle($commit);

    $notification = Notification::query()->sole();

    expect($notification->recipient_id)->toBe($watcher->id)
        ->and($notification->type)->toBe(NotificationType::Committed)
        ->and($notification->actor_id)->toBe($author->id)
        ->and($notification->commit_id)->toBe($commit->id)
        ->and($watcher->notifications()->relevant()->count())->toBeOne();
});

it('does not notify about commits that are not approved', function (): void {
    $author = User::factory()->create();
    User::factory()->create()->following()->attach($author, ['watching' => true]);

    resolve(CreateWatchNotifications::class)->handle(
        Commit::factory()->for($author)->pending()->create(),
        Commit::factory()->for($author)->rejected()->create(),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('leaves replies out', function (): void {
    $author = User::factory()->create();
    User::factory()->create()->following()->attach($author, ['watching' => true]);
    $reply = Commit::factory()->for($author)->for(Commit::factory(), 'parent')->create();

    resolve(CreateWatchNotifications::class)->handle($reply);

    expect(Notification::query()->where('type', NotificationType::Committed)->count())->toBe(0);
});

it('leaves people the commit mentions or quotes to those notifications', function (): void {
    $author = User::factory()->create();
    $mentioned = User::factory()->create(['handle' => 'nunomaduro']);
    $quoted = Commit::factory()->create();
    $mentioned->following()->attach($author, ['watching' => true]);
    $quoted->user->following()->attach($author, ['watching' => true]);
    $commit = Commit::factory()->for($author)->for($quoted, 'quotedCommit')->create([
        'body' => 'Hi @nunomaduro',
        'mentions' => ['nunomaduro' => $mentioned->id],
    ]);

    resolve(CreateWatchNotifications::class)->handle($commit);

    expect(Notification::query()->where('type', NotificationType::Committed)->count())->toBe(0);
});

it('does not notify watchers who muted the author', function (): void {
    $author = User::factory()->create();
    $watcher = User::factory()->create();
    $watcher->following()->attach($author, ['watching' => true]);
    $watcher->muting()->attach($author);

    resolve(CreateWatchNotifications::class)->handle(Commit::factory()->for($author)->create());

    expect(Notification::query()->count())->toBe(0);
});

it('is idempotent', function (): void {
    $author = User::factory()->create();
    User::factory()->create()->following()->attach($author, ['watching' => true]);
    $commit = Commit::factory()->for($author)->create();

    $action = resolve(CreateWatchNotifications::class);
    $action->handle($commit);
    $action->handle($commit);

    expect(Notification::query()->count())->toBeOne();
});
