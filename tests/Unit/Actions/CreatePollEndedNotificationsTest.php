<?php

declare(strict_types=1);

use App\Actions\CreatePollEndedNotifications;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;

it('tells every voter the results are in', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voters = User::factory()->count(2)->create();

    foreach ($voters as $index => $voter) {
        PollVote::factory()->create(['poll_option_id' => $poll->options[$index]->id, 'user_id' => $voter->id]);
    }

    resolve(CreatePollEndedNotifications::class)->handle($poll);

    expect($voters[0]->notifications()->sole()->type)->toBe(NotificationType::PollEnded)
        ->and($voters[1]->notifications()->sole()->type)->toBe(NotificationType::PollEnded);
});

it("names the poll author as the actor, and the poll's commit as the subject", function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    resolve(CreatePollEndedNotifications::class)->handle($poll);

    $notification = $voter->notifications()->sole();

    expect($notification->actor_id)->toBe($poll->commit->user_id)
        ->and($notification->commit_id)->toBe($poll->commit_id)
        ->and($notification->subject_id)->toBe($poll->commit_id);
});

it('does not notify the author', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    resolve(CreatePollEndedNotifications::class)->handle($poll);

    expect($poll->commit->user->notifications()->where('type', NotificationType::PollEnded)->exists())->toBeFalse();
});

it('is idempotent', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    $action = resolve(CreatePollEndedNotifications::class);

    $action->handle($poll);
    $action->handle($poll);

    expect($voter->notifications()->count())->toBeOne();
});

it('notifies nobody when nobody voted', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();

    resolve(CreatePollEndedNotifications::class)->handle($poll);

    expect(Notification::query()->where('type', NotificationType::PollEnded)->doesntExist())->toBeTrue();
});
