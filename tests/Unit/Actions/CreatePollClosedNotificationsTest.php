<?php

declare(strict_types=1);

use App\Actions\CreatePollClosedNotifications;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\PollVote;

it('notifies the author and every voter, as the author', function (): void {
    $poll = Poll::factory()->closed()->withOptions('Tabs', 'Spaces')->create();
    $votes = PollVote::factory()->count(2)->sequence(
        ['poll_option_id' => $poll->options[0]->id],
        ['poll_option_id' => $poll->options[1]->id],
    )->create();

    resolve(CreatePollClosedNotifications::class)->handle($poll);

    $notifications = Notification::query()->get();

    expect($notifications->pluck('recipient_id')->all())->toEqualCanonicalizing([$poll->commit->user_id, ...$votes->pluck('user_id')])
        ->and($notifications->pluck('actor_id')->unique()->all())->toBe([$poll->commit->user_id])
        ->and($notifications->pluck('type')->unique()->all())->toBe([NotificationType::PollClosed])
        ->and($notifications->pluck('commit_id')->unique()->all())->toBe([$poll->commit_id]);
});

it('is idempotent', function (): void {
    $poll = Poll::factory()->closed()->create();
    $action = resolve(CreatePollClosedNotifications::class);

    $action->handle($poll);
    $action->handle($poll);

    expect(Notification::query()->count())->toBeOne();
});

it('does not notify about polls on commits that are no longer approved', function (Commit $commit): void {
    $poll = Poll::factory()->closed()->for($commit)->create();

    resolve(CreatePollClosedNotifications::class)->handle($poll->fresh());

    expect(Notification::query()->exists())->toBeFalse();
})->with([
    'rejected' => fn (): Commit => Commit::factory()->rejected()->create(),
    'deleted' => fn (): Commit => tap(Commit::factory()->create())->delete(),
]);
