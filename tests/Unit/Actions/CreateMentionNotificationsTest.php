<?php

declare(strict_types=1);

use App\Actions\CreateMentionNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('notifies everyone mentioned in approved commits', function (): void {
    $nuno = User::factory()->create(['username' => 'nunomaduro']);
    $taylor = User::factory()->create(['username' => 'TaylorOtwell']);
    $commit = Commit::factory()->create([
        'body' => 'Pairing with @NunoMaduro and @taylorotwell',
        'mentions' => ['nunomaduro' => $nuno->id, 'taylorotwell' => $taylor->id],
    ]);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle($commit);

    foreach ([$nuno, $taylor] as $user) {
        $notification = $user->notifications()->sole();

        expect($notification->type)->toBe(NotificationType::Mentioned)
            ->and($notification->actor_id)->toBe($commit->user_id)
            ->and($notification->commit_id)->toBe($commit->id);
    }
});

it('does not notify about commits that are not approved', function (): void {
    $user = User::factory()->create(['username' => 'nunomaduro']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle(
        Commit::factory()->pending()->create(['body' => 'Hi @nunomaduro', 'mentions' => ['nunomaduro' => $user->id]]),
        Commit::factory()->rejected()->create(['body' => 'Hi @nunomaduro', 'mentions' => ['nunomaduro' => $user->id]]),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('ignores usernames nobody has', function (): void {
    $action = resolve(CreateMentionNotifications::class);

    $action->handle(Commit::factory()->create(['body' => 'Hi @nobody']));

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors mentioning themselves', function (): void {
    $user = User::factory()->create(['username' => 'nunomaduro']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle(Commit::factory()->for($user)->create(['body' => 'Me, @nunomaduro', 'mentions' => ['nunomaduro' => $user->id]]));

    expect(Notification::query()->count())->toBe(0);
});

it('leaves the parent author of a reply to the reply notification', function (): void {
    $parent = Commit::factory()->for(User::factory()->state(['username' => 'nunomaduro']))->create();
    $other = User::factory()->create(['username' => 'taylorotwell']);
    $reply = Commit::factory()->for($parent, 'parent')->create([
        'body' => '@nunomaduro @taylorotwell look',
        'mentions' => ['nunomaduro' => $parent->user_id, 'taylorotwell' => $other->id],
    ]);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle($reply);

    expect(Notification::query()->sole()->recipient_id)->toBe($other->id);
});

it('is idempotent', function (): void {
    $user = User::factory()->create(['username' => 'nunomaduro']);
    $commit = Commit::factory()->create(['body' => 'Hi @nunomaduro', 'mentions' => ['nunomaduro' => $user->id]]);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle($commit);
    $user->update(['username' => 'renamed']);
    $replacement = User::factory()->create(['username' => 'nunomaduro']);
    $action->handle($commit);

    expect(Notification::query()->count())->toBeOne();
    expect($user->notifications()->count())->toBeOne();
    expect($replacement->notifications()->count())->toBe(0);
});

it('does nothing without commits', function (): void {
    $action = resolve(CreateMentionNotifications::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});
