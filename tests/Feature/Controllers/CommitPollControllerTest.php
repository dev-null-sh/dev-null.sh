<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('shows the poll as the viewer sees it, hiding results from whoever has not voted until it closes', function (): void {
    $voter = User::factory()->create();
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $poll = $commit->poll()->create(['duration' => 60, 'opened_at' => now()]);
    $yes = $poll->options()->create(['label' => 'Yes', 'position' => 0]);
    $poll->options()->create(['label' => 'No', 'position' => 1]);
    $this->actingAs($voter)->post(route('commit-poll-vote.store', $commit), ['option' => $yes->id]);

    $this->actingAs($voter)->getJson(route('commit-poll.show', $commit))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('poll.has_voted', true)
        ->assertJsonPath('poll.total_votes', 1);
    $this->actingAs($viewer)->getJson(route('commit-poll.show', $commit))
        ->assertJsonPath('poll.has_voted', false)
        ->assertJsonPath('poll.total_votes', null);

    $this->travel(61)->minutes();

    $this->actingAs($viewer)->getJson(route('commit-poll.show', $commit))
        ->assertJsonPath('poll.is_closed', true)
        ->assertJsonPath('poll.total_votes', 1);
});

it('hides the poll of a commit the viewer cannot see, and of a commit without one', function (): void {
    $pending = Commit::factory()->pending()->create();
    $pending->poll()->create(['duration' => 60, 'opened_at' => null]);

    $this->getJson(route('commit-poll.show', $pending))->assertNotFound();
    $this->getJson(route('commit-poll.show', Commit::factory()->create()))->assertNotFound();
});
