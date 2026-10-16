<?php

declare(strict_types=1);

use App\Actions\CreateQuoteNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;

it('notifies the quoted author about approved quotes', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    $notification = $quoted->user->notifications()->sole();

    expect($notification->type)->toBe(NotificationType::Quoted)
        ->and($notification->actor_id)->toBe($quote->user_id)
        ->and($notification->commit_id)->toBe($quote->id);
});

it('does not notify about commits that are not approved quotes', function (): void {
    $quoted = Commit::factory()->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle(
        Commit::factory()->create(),
        Commit::factory()->pending()->for($quoted, 'quotedCommit')->create(),
        Commit::factory()->rejected()->for($quoted, 'quotedCommit')->create(),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify about quotes of deleted commits', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();
    $quoted->delete();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors quoting themselves', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->for($quoted->user)->for($quoted, 'quotedCommit')->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);

    expect(Notification::query()->count())->toBe(0);
});

it('is idempotent', function (): void {
    $quoted = Commit::factory()->create();
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();

    $action = resolve(CreateQuoteNotifications::class);

    $action->handle($quote);
    $action->handle($quote);

    expect(Notification::query()->count())->toBeOne();
});

it('does nothing without commits', function (): void {
    $action = resolve(CreateQuoteNotifications::class);

    $action->handle();

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify authors who muted the quoter or are across a block', function (string $relation, bool $quotedAuthorActs): void {
    $quoted = Commit::factory()->create();
    $silenced = Commit::factory()->for($quoted, 'quotedCommit')->create();
    $notified = Commit::factory()->for($quoted, 'quotedCommit')->create();

    [$actor, $subject] = $quotedAuthorActs ? [$quoted->user, $silenced->user] : [$silenced->user, $quoted->user];
    $actor->{$relation}()->attach($subject);

    resolve(CreateQuoteNotifications::class)->handle($silenced, $notified);

    $notification = Notification::query()->sole();

    expect($notification->actor_id)->toBe($notified->user_id)
        ->and($notification->commit_id)->toBe($notified->id);
})->with([
    'the author muted the quoter' => ['muting', true],
    'the author blocked the quoter' => ['blocking', true],
    'the quoter blocked the author' => ['blocking', false],
]);
