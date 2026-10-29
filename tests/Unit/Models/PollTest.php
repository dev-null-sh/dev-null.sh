<?php

declare(strict_types=1);

use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

function pollFor(User $viewer, Poll $poll): Poll
{
    $commit = Commit::query()->withPollFor($viewer)->findOrFail($poll->commit_id);

    assert($commit->poll instanceof Poll);

    return $commit->poll;
}

it('belongs to a commit and lists its options in order', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    expect($poll->commit->poll->is($poll))->toBeTrue()
        ->and($poll->options->pluck('label')->all())->toBe(['Tabs', 'Spaces'])
        ->and($poll->options->first()->poll->is($poll))->toBeTrue();
});

it('opens for the length of its duration', function (): void {
    $poll = Poll::factory()->create(['duration' => PollDuration::OneHour, 'opened_at' => now()]);

    expect($poll->duration)->toBe(PollDuration::OneHour)
        ->and($poll->closesAt()?->toIso8601String())->toBe(now()->addHour()->toIso8601String())
        ->and($poll->isClosed())->toBeFalse();

    $this->travel(1)->hour();

    expect($poll->isClosed())->toBeTrue();
});

it('is not closed before it is opened', function (): void {
    $poll = Poll::factory()->unopened()->create();

    expect($poll->closesAt())->toBeNull()
        ->and($poll->isClosed())->toBeFalse();
});

it('hides the results from viewers who have not voted', function (): void {
    $viewer = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create(['opened_at' => now()]);
    PollVote::factory()->for($poll->options->first(), 'option')->create();

    expect(pollFor($viewer, $poll)->toArray())->toBe([
        'id' => $poll->id,
        'closes_at' => now()->addDay()->startOfSecond()->toJSON(),
        'is_closed' => false,
        'has_voted' => false,
        'total_votes' => null,
        'options' => [
            ['id' => $poll->options[0]->id, 'label' => 'Tabs', 'votes_count' => null, 'voted' => false],
            ['id' => $poll->options[1]->id, 'label' => 'Spaces', 'votes_count' => null, 'voted' => false],
        ],
    ]);
});

it('shows the results and the own choice to voters', function (): void {
    $viewer = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->count(2)->create();
    PollVote::factory()->for($poll->options[1], 'option')->for($viewer)->create();

    $serialized = pollFor($viewer, $poll)->toArray();

    expect($serialized['has_voted'])->toBeTrue()
        ->and($serialized['total_votes'])->toBe(3)
        ->and($serialized['options'][0])->toMatchArray(['votes_count' => 2, 'voted' => false])
        ->and($serialized['options'][1])->toMatchArray(['votes_count' => 1, 'voted' => true]);
});

it('shows the results to the author', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->create();

    $serialized = pollFor($poll->commit->user, $poll)->toArray();

    expect($serialized['has_voted'])->toBeFalse()
        ->and($serialized['total_votes'])->toBe(1)
        ->and(array_column($serialized['options'], 'votes_count'))->toBe([1, 0]);
});

it('shows the final results to everyone once closed', function (): void {
    $viewer = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create();
    PollVote::factory()->for($poll->options[1], 'option')->create();

    $serialized = pollFor($viewer, $poll)->toArray();

    expect($serialized['is_closed'])->toBeTrue()
        ->and($serialized['total_votes'])->toBe(1)
        ->and(array_column($serialized['options'], 'votes_count'))->toBe([0, 1]);
});

it('has no closing time before it is opened', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->unopened()->create();

    expect(pollFor($poll->commit->user, $poll)->toArray())->toMatchArray([
        'closes_at' => null,
        'is_closed' => false,
    ]);
});

it('never exposes who voted', function (): void {
    $voter = User::factory()->create(['handle' => 'secret-voter']);
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($voter)->create();

    expect(json_encode(pollFor(User::factory()->create(), $poll)))
        ->not->toContain($voter->id)
        ->not->toContain('secret-voter');
});

it('is removed with its commit, and votes with their voter', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $voter = User::factory()->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($voter)->create();
    PollVote::factory()->for($poll->options[1], 'option')->create();

    $voter->delete();

    expect(PollVote::query()->count())->toBe(1);

    $poll->commit->forceDelete();

    expect(Poll::query()->count())->toBe(0)
        ->and(PollOption::query()->count())->toBe(0)
        ->and(PollVote::query()->count())->toBe(0);
});

it('allows one vote per user and poll', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $vote = PollVote::factory()->for($poll->options[0], 'option')->create();

    expect(fn () => PollVote::factory()->for($poll->options[1], 'option')->create(['user_id' => $vote->user_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('belongs to an option, its poll and its voter', function (): void {
    $vote = PollVote::factory()->create();

    expect($vote->option->poll->is($vote->poll))->toBeTrue()
        ->and($vote->user->pollVotes->sole()->is($vote))->toBeTrue()
        ->and($vote->poll->votes->sole()->is($vote))->toBeTrue()
        ->and($vote->option->votes->sole()->is($vote))->toBeTrue();
});
