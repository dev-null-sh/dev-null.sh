<?php

declare(strict_types=1);

use App\Actions\VoteOnPoll;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('records a vote for an option', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    resolve(VoteOnPoll::class)->handle($user, $poll->options[1]);

    $vote = PollVote::query()->sole();

    expect($vote->user_id)->toBe($user->id)
        ->and($vote->poll_id)->toBe($poll->id)
        ->and($vote->poll_option_id)->toBe($poll->options[1]->id);
});

it('refuses votes that are not allowed', function (Closure $scenario, string $message): void {
    [$user, $poll] = $scenario();

    expect(fn () => resolve(VoteOnPoll::class)->handle($user, $poll->options[0]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'own poll' => [function (): array {
        $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

        return [$poll->commit->user, $poll];
    }, "You can't vote on your own poll."],
    'closed poll' => [fn (): array => [User::factory()->create(), Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create()], 'This poll has closed.'],
    'unopened poll' => [fn (): array => [User::factory()->create(), Poll::factory()->withOptions('Tabs', 'Spaces')->unopened()->create()], 'This poll is not open for votes.'],
    'pending commit' => [fn (): array => [User::factory()->create(), Poll::factory()->for(Commit::factory()->pending())->withOptions('Tabs', 'Spaces')->create()], 'This poll is not open for votes.'],
    'rejected commit' => [fn (): array => [User::factory()->create(), Poll::factory()->for(Commit::factory()->rejected())->withOptions('Tabs', 'Spaces')->create()], 'This poll is not open for votes.'],
    'deleted commit' => [fn (): array => [User::factory()->create(), Poll::factory()->for(Commit::factory()->state(['deleted_at' => now()]))->withOptions('Tabs', 'Spaces')->create()], 'This poll is not open for votes.'],
]);

it('refuses a second vote on the same poll', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $action = resolve(VoteOnPoll::class);

    $action->handle($user, $poll->options[0]);

    expect(fn () => $action->handle($user, $poll->options[1]))
        ->toThrow(ValidationException::class, 'You have already voted on this poll.')
        ->and(PollVote::query()->sole()->poll_option_id)->toBe($poll->options[0]->id);
});
