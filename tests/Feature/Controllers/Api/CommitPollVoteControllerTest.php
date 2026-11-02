<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('votes and answers with the commit, whose poll now shows the results', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->count(2)->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[1]->id]);

    $response->assertOk()
        ->assertJsonPath('data.id', $poll->commit_id)
        ->assertJsonPath('data.poll.has_voted', true)
        ->assertJsonPath('data.poll.total_votes', 3)
        ->assertJsonPath('data.poll.options.0.votes_count', 2)
        ->assertJsonPath('data.poll.options.0.voted', false)
        ->assertJsonPath('data.poll.options.0.percentage', 67)
        ->assertJsonPath('data.poll.options.1.votes_count', 1)
        ->assertJsonPath('data.poll.options.1.voted', true)
        ->assertJsonPath('data.poll.options.1.percentage', 33);

    expect($this->user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[1]->id);
});

it('answers with the commit as the commit endpoint shows it', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id]);

    $response->assertOk()
        ->assertExactJson(['data' => signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $poll->commit))->json('data')]);
});

it('requires an option of the poll', function (Closure $option, string $message): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $option()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option' => $message]);

    expect(PollVote::query()->exists())->toBeFalse();
})->with([
    'missing' => [fn (): null => null, 'The option field is required.'],
    'not a uuid' => [fn (): string => 'tabs', 'The option field must be a valid UUID.'],
    'of another poll' => [fn (): string => Poll::factory()->withOptions('Vim', 'Emacs')->create()->options[0]->id, 'The selected option is invalid.'],
]);

it('may not vote on a commit without a poll', function (): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $commit), ['option' => Poll::factory()->withOptions('Vim', 'Emacs')->create()->options[0]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option' => 'The selected option is invalid.']);
});

it('explains why a vote is refused, as the web does', function (Closure $poll, string $message): void {
    $poll = $poll($this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option' => $message]);

    expect(PollVote::query()->exists())->toBeFalse();
})->with([
    'own poll' => [fn (User $user): Poll => Poll::factory()->for(Commit::factory()->for($user))->withOptions('Tabs', 'Spaces')->create(), "You can't vote on your own poll."],
    'own pending poll' => [fn (User $user): Poll => Poll::factory()->for(Commit::factory()->for($user)->pending())->withOptions('Tabs', 'Spaces')->unopened()->create(), 'This poll is not open for votes.'],
    'unopened poll' => [fn (User $user): Poll => Poll::factory()->withOptions('Tabs', 'Spaces')->unopened()->create(), 'This poll is not open for votes.'],
    'closed poll' => [fn (User $user): Poll => Poll::factory()->withOptions('Tabs', 'Spaces')->closed()->create(), 'This poll has closed.'],
]);

it('refuses a second vote, since votes are final', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[1]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option' => 'You have already voted on this poll.']);

    expect($this->user->pollVotes()->sole()->poll_option_id)->toBe($poll->options[0]->id);
});

it('may not vote on a poll the user may not see', function (string $state): void {
    $poll = Poll::factory()->for(Commit::factory()->{$state}())->withOptions('Tabs', 'Spaces')->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertNotFound()
        ->assertDontSee($poll->commit->body);

    expect(PollVote::query()->exists())->toBeFalse();
})->with(['pending', 'rejected']);

it('may not vote across a block in either direction', function (bool $voterBlocks): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $author = $poll->commit->user;

    $voterBlocks ? $this->user->blocking()->attach($author) : $author->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertNotFound();

    expect(PollVote::query()->exists())->toBeFalse();
})->with([true, false]);

it('may still vote across a mute, as on the web', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $this->user->muting()->attach($poll->commit->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertOk()
        ->assertJsonPath('data.poll.has_voted', true);
});

it('may not vote on a deleted or unknown commit', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $poll->commit->delete();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-poll-vote.store', $poll->commit_id), ['option' => $poll->options[0]->id])
        ->assertNotFound();

    signedApiRequest($this->keyPair, 'POST', 'api/v1/commits/0199e3a4-0000-7000-8000-000000000000/poll/votes', ['option' => $poll->options[0]->id])
        ->assertNotFound();
});

it('requires a signed request', function (): void {
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();

    $this->postJson(route('api-commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        ->assertUnauthorized();

    expect(PollVote::query()->exists())->toBeFalse();
});
