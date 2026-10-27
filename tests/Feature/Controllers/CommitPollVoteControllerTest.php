<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;

it('may vote and gets the results back', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->count(2)->create();

    $response = $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[1]->id]);

    $response->assertOk()
        ->assertJsonPath('poll.has_voted', true)
        ->assertJsonPath('poll.total_votes', 3)
        ->assertJsonPath('poll.options.0.votes_count', 2)
        ->assertJsonPath('poll.options.0.voted', false)
        ->assertJsonPath('poll.options.1.votes_count', 1)
        ->assertJsonPath('poll.options.1.voted', true);

    expect($user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[1]->id);
});

it('requires an option of the poll', function (Closure $option): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $option()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('option');

    expect(PollVote::query()->count())->toBe(0);
})->with([
    'missing' => fn (): null => null,
    'not a uuid' => fn (): string => 'tabs',
    'of another poll' => fn (): string => Poll::factory()->withOptions('Vim', 'Emacs')->create()->options[0]->id,
]);

it('may not vote on a commit without a poll', function (): void {
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $commit), ['option' => Poll::factory()->withOptions('Vim', 'Emacs')->create()->options[0]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('option');
});

it('explains why a vote is refused', function (Closure $scenario, string $message): void {
    [$user, $poll] = $scenario();

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option' => $message]);

    expect(PollVote::query()->count())->toBe(0);
})->with([
    'own poll' => [function (): array {
        $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

        return [$poll->commit->user, $poll];
    }, "You can't vote on your own poll."],
    'own pending poll' => [function (): array {
        $poll = Poll::factory()->for(Commit::factory()->pending())->withOptions('Tabs', 'Spaces')->unopened()->create();

        return [$poll->commit->user, $poll];
    }, 'This poll is not open for votes.'],
    'own rejected poll' => [function (): array {
        $poll = Poll::factory()->for(Commit::factory()->rejected())->withOptions('Tabs', 'Spaces')->create();

        return [$poll->commit->user, $poll];
    }, 'This poll is not open for votes.'],
    'unopened poll' => [fn (): array => [User::factory()->create(), Poll::factory()->withOptions('Tabs', 'Spaces')->unopened()->create()], 'This poll is not open for votes.'],
    'closed poll' => [fn (): array => [User::factory()->create(), Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create()], 'This poll has closed.'],
]);

it('returns the existing vote and results after a duplicate web vote', function (int $option): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($user)->create();
    PollVote::factory()->for($poll->options[1], 'option')->create();

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[$option]->id])
        ->assertOk()
        ->assertJsonPath('poll.has_voted', true)
        ->assertJsonPath('poll.total_votes', 2)
        ->assertJsonPath('poll.options.0.votes_count', 1)
        ->assertJsonPath('poll.options.0.voted', true)
        ->assertJsonPath('poll.options.1.votes_count', 1)
        ->assertJsonPath('poll.options.1.voted', false);

    expect($user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[0]->id)
        ->and(PollVote::query()->where('poll_id', $poll->id)->count())->toBe(2);
})->with([0, 1]);

it('may not vote on a poll that is not visible', function (string $state): void {
    $poll = Poll::factory()->for(Commit::factory()->{$state}())->withOptions('Tabs', 'Spaces')->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
})->with(['pending', 'rejected']);

it('may not vote across a block in either direction', function (bool $voterBlocks): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $author = $poll->commit->user;

    $voterBlocks ? $user->blocking()->attach($author) : $author->blocking()->attach($user);

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
})->with([true, false]);

it('may still vote across a mute in either direction', function (bool $voterMutes): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $author = $poll->commit->user;

    $voterMutes ? $user->muting()->attach($author) : $author->muting()->attach($user);

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertOk()
        ->assertJsonPath('poll.has_voted', true);

    expect($user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[0]->id);
})->with([true, false]);

it('may not vote on a deleted commit', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $poll->commit->delete();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-poll-vote.store', $poll->commit_id), ['option' => $poll->options[0]->id])
        ->assertNotFound();
});

it('requires authentication to vote', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $this->post(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertRedirectToRoute('home');

    expect(PollVote::query()->count())->toBe(0);
});

it('returns the results without recording a vote', function (): void {
    $user = User::factory()->create();
    $voter = User::factory()->create(['handle' => 'secret-voter']);
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($voter)->create();
    PollVote::factory()->for($poll->options[0], 'option')->create();

    $loaded = Commit::query()->withPollFor($user)->findOrFail($poll->commit_id)->poll;
    assert($loaded instanceof Poll);

    expect($loaded->toArray())->toMatchArray([
        'has_voted' => false,
        'total_votes' => null,
    ]);

    $response = $this->actingAs($user)->getJson(route('commit-poll.show', $poll->commit));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', 2)
        ->assertJsonPath('poll.options.0.votes_count', 2)
        ->assertJsonPath('poll.options.0.voted', false)
        ->assertJsonPath('poll.options.1.votes_count', 0)
        ->assertJsonPath('poll.options.1.voted', false);

    expect($response->getContent())->not->toContain($voter->id)
        ->and($response->getContent())->not->toContain('secret-voter')
        ->and(PollVote::query()->count())->toBe(2);

    $this->actingAs($user)->getJson(route('commit-poll.show', $poll->commit))
        ->assertOk()
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', 2);

    expect(PollVote::query()->count())->toBe(2);
});

it('requires authentication to view poll results', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $this->get(route('commit-poll.show', $poll->commit))
        ->assertRedirectToRoute('home');

    expect(PollVote::query()->count())->toBe(0);
});

it('may not view results of a poll that is not visible', function (string $state): void {
    $poll = Poll::factory()->for(Commit::factory()->{$state}())->withOptions('Tabs', 'Spaces')->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
})->with(['pending', 'rejected']);

it('may not view results across a block in either direction', function (bool $viewerBlocks): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $author = $poll->commit->user;

    $viewerBlocks ? $user->blocking()->attach($author) : $author->blocking()->attach($user);

    $this->actingAs($user)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
})->with([true, false]);

it('may still view results across a mute in either direction', function (bool $viewerMutes): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->create();
    $author = $poll->commit->user;

    $viewerMutes ? $user->muting()->attach($author) : $author->muting()->attach($user);

    $this->actingAs($user)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertOk()
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', 1);

    expect(PollVote::query()->where('user_id', $user->id)->count())->toBe(0);
})->with([true, false]);

it('may not view results before the poll opens', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->unopened()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
});

it('may not view results of a commit without a poll', function (): void {
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-poll.show', $commit))
        ->assertNotFound();
});

it('may not view results of a deleted commit', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $poll->commit->delete();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-poll.show', $poll->commit_id))
        ->assertNotFound();
});

it('does not reveal a pending poll through the results endpoint', function (): void {
    $poll = Poll::factory()->for(Commit::factory()->pending())->withOptions('Tabs', 'Spaces')->unopened()->create();

    $this->actingAs($poll->commit->user)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertForbidden();

    expect(PollVote::query()->count())->toBe(0);
});

it('still shows the author the results without a vote', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->create();
    $author = $poll->commit->user;

    $loaded = Commit::query()->withPollFor($author)->findOrFail($poll->commit_id)->poll;
    assert($loaded instanceof Poll);

    expect($loaded->toArray())->toMatchArray([
        'has_voted' => false,
        'total_votes' => 1,
    ]);

    $this->actingAs($author)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertOk()
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', 1)
        ->assertJsonPath('poll.options.0.votes_count', 1)
        ->assertJsonPath('poll.options.0.voted', false);

    expect($author->pollVotes()->count())->toBe(0);
});

it('still shows a closed poll to someone who has not voted', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create();
    PollVote::factory()->for($poll->options[1], 'option')->create();

    $loaded = Commit::query()->withPollFor($user)->findOrFail($poll->commit_id)->poll;
    assert($loaded instanceof Poll);

    expect($loaded->toArray())->toMatchArray([
        'is_closed' => true,
        'has_voted' => false,
        'total_votes' => 1,
    ]);

    $this->actingAs($user)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertOk()
        ->assertJsonPath('poll.is_closed', true)
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', 1)
        ->assertJsonPath('poll.options.0.votes_count', 0)
        ->assertJsonPath('poll.options.1.votes_count', 1);

    expect($user->pollVotes()->count())->toBe(0)
        ->and(PollVote::query()->count())->toBe(1);
});

it('records exactly one vote after the results were viewed', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->create();

    $this->actingAs($user)
        ->getJson(route('commit-poll.show', $poll->commit))
        ->assertOk()
        ->assertJsonPath('poll.has_voted', false);

    $this->actingAs($user)
        ->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[1]->id])
        ->assertOk()
        ->assertJsonPath('poll.has_voted', true)
        ->assertJsonPath('poll.total_votes', 2)
        ->assertJsonPath('poll.options.1.votes_count', 1)
        ->assertJsonPath('poll.options.1.voted', true);

    expect($user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[1]->id)
        ->and(PollVote::query()->where('poll_id', $poll->id)->count())->toBe(2);
});
