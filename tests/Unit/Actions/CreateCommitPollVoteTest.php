<?php

declare(strict_types=1);

use App\Actions\CreateCommitPollVote;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;

it('records a vote for an option', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $recorded = resolve(CreateCommitPollVote::class)->handle($user, $poll->options[1]);

    $vote = PollVote::query()->sole();

    expect($recorded)->toBeTrue()
        ->and($vote->user_id)->toBe($user->id)
        ->and($vote->poll_id)->toBe($poll->id)
        ->and($vote->poll_option_id)->toBe($poll->options[1]->id);
});

it('keeps the first vote when the same voter votes again', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $action = resolve(CreateCommitPollVote::class);

    $action->handle($user, $poll->options[0]);

    expect($action->handle($user, $poll->options[1]))->toBeFalse()
        ->and(PollVote::query()->sole()->poll_option_id)->toBe($poll->options[0]->id);
});
