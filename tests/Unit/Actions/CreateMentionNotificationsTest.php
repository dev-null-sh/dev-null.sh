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
    $commit = Commit::factory()->create(['body' => 'Pairing with @NunoMaduro and @taylorotwell']);

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
    User::factory()->create(['username' => 'nunomaduro']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle(
        Commit::factory()->pending()->create(['body' => 'Hi @nunomaduro']),
        Commit::factory()->rejected()->create(['body' => 'Hi @nunomaduro']),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('ignores usernames nobody has', function (): void {
    $action = resolve(CreateMentionNotifications::class);

    $action->handle(Commit::factory()->create(['body' => 'Hi @nobody']));

    expect(Notification::query()->count())->toBe(0);
});

it('does not send mentions without an author identity', function (): void {
    User::factory()->create(['username' => 'nunomaduro']);
    $commit = Commit::factory()->create(['user_id' => null, 'body' => 'Hi @nunomaduro']);

    resolve(CreateMentionNotifications::class)->handle($commit);

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors mentioning themselves', function (): void {
    $user = User::factory()->create(['username' => 'nunomaduro']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle(Commit::factory()->for($user)->create(['body' => 'Me, @nunomaduro']));

    expect(Notification::query()->count())->toBe(0);
});

it('leaves the parent author of a reply to the reply notification', function (): void {
    $parent = Commit::factory()->for(User::factory()->state(['username' => 'nunomaduro']))->create();
    $other = User::factory()->create(['username' => 'taylorotwell']);
    $reply = Commit::factory()->for($parent, 'parent')->create(['body' => '@nunomaduro @taylorotwell look']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle($reply);

    expect(Notification::query()->sole()->recipient_id)->toBe($other->id);
});

it('is idempotent', function (): void {
    User::factory()->create(['username' => 'nunomaduro']);
    $commit = Commit::factory()->create(['body' => 'Hi @nunomaduro']);

    $action = resolve(CreateMentionNotifications::class);

    $action->handle($commit);
    $action->handle($commit);

    expect(Notification::query()->count())->toBeOne();
});

it('does not notify people who muted the author or are across a block', function (string $relation, bool $mentionedActs): void {
    $mentioned = User::factory()->create(['username' => 'nunomaduro']);
    $other = User::factory()->create(['username' => 'taylorotwell']);
    $silenced = Commit::factory()->create(['body' => 'Hi @nunomaduro']);
    $notified = Commit::factory()->create(['body' => 'Hi @nunomaduro and @taylorotwell']);

    [$actor, $subject] = $mentionedActs ? [$mentioned, $silenced->user] : [$silenced->user, $mentioned];
    $actor->{$relation}()->attach($subject);

    resolve(CreateMentionNotifications::class)->handle($silenced, $notified);

    expect($mentioned->notifications()->sole()->commit_id)->toBe($notified->id)
        ->and($other->notifications()->sole()->commit_id)->toBe($notified->id);
})->with([
    'the mentioned user muted the author' => ['muting', true],
    'the mentioned user blocked the author' => ['blocking', true],
    'the author blocked the mentioned user' => ['blocking', false],
]);

it('does nothing without commits', function (): void {
    $action = resolve(CreateMentionNotifications::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});
