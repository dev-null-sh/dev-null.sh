<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\MergeConflict;
use App\Models\MergeConflictVote;
use App\Models\User;
use App\Services\Features;

it('lets a reader pick a side, then shows them the count', function (): void {
    $mergeConflict = MergeConflict::factory()->create();
    MergeConflictVote::factory()->for($mergeConflict)->create(['side' => 'theirs']);

    $this->actingAs($voter = User::factory()->create())
        ->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'ours'])
        ->assertOk()
        ->assertJsonPath('mergeConflict.side', 'ours')
        ->assertJsonPath('mergeConflict.can_vote', false)
        ->assertJsonPath('mergeConflict.ours.votes', 1)
        ->assertJsonPath('mergeConflict.theirs.votes', 1);

    $this->assertDatabaseHas('merge_conflict_votes', ['merge_conflict_id' => $mergeConflict->id, 'user_id' => $voter->id, 'side' => 'ours']);
});

it('keeps the first side a reader picked', function (): void {
    $mergeConflict = MergeConflict::factory()->create();
    $voter = User::factory()->create();
    MergeConflictVote::factory()->for($mergeConflict)->for($voter)->create(['side' => 'ours']);

    $this->actingAs($voter)
        ->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'theirs'])
        ->assertOk()
        ->assertJsonPath('mergeConflict.side', 'ours');

    expect($mergeConflict->votes()->count())->toBe(1);
});

it('does not let the authors of either side vote', function (string $side): void {
    $mergeConflict = MergeConflict::factory()->create();

    $this->actingAs($mergeConflict->{$side}->user)
        ->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'ours'])
        ->assertForbidden();
})->with(['ours', 'theirs']);

it('does not take votes once the conflict closed', function (): void {
    $mergeConflict = MergeConflict::factory()->closed()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'ours'])
        ->assertForbidden();
});

it('only takes ours or theirs', function (): void {
    $mergeConflict = MergeConflict::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'both'])
        ->assertJsonValidationErrors('side');
});

it('requires authentication to vote', function (): void {
    $mergeConflict = MergeConflict::factory()->create();

    $this->postJson(route('merge-conflict-vote.store', $mergeConflict->commit), ['side' => 'ours'])
        ->assertUnauthorized();
});

it('shows the merge conflict on the commit, hiding the count until the reader picks a side', function (): void {
    $mergeConflict = MergeConflict::factory()->create();
    MergeConflictVote::factory()->for($mergeConflict)->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', ['user' => $mergeConflict->commit->user, 'commit' => $mergeConflict->commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mergeConflict.ours.id', $mergeConflict->ours_id)
            ->where('mergeConflict.ours.body', $mergeConflict->ours->body)
            ->where('mergeConflict.ours.user.handle', $mergeConflict->ours->user->handle)
            ->where('mergeConflict.theirs.id', $mergeConflict->theirs_id)
            ->where('mergeConflict.ours.votes', null)
            ->where('mergeConflict.side', null)
            ->where('mergeConflict.can_vote', true)
            ->where('mergeConflict.is_closed', false));
});

it('shows everyone the final count once the conflict closed', function (): void {
    $mergeConflict = MergeConflict::factory()->closed()->create();
    MergeConflictVote::factory()->for($mergeConflict)->count(2)->create(['side' => 'theirs']);

    $this->get(route('commit.show', ['user' => $mergeConflict->commit->user, 'commit' => $mergeConflict->commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mergeConflict.is_closed', true)
            ->where('mergeConflict.ours.votes', 0)
            ->where('mergeConflict.theirs.votes', 2));
});

it('hides the merge conflict when the reader cannot see one side', function (): void {
    $mergeConflict = MergeConflict::factory()->create();
    $reader = User::factory()->create();
    $reader->blocking()->attach($mergeConflict->theirs->user);

    $this->actingAs($reader)
        ->get(route('commit.show', ['user' => $mergeConflict->commit->user, 'commit' => $mergeConflict->commit]))
        ->assertInertia(fn ($page) => $page->where('mergeConflict', null));
});

it('hides merge conflicts while they are switched off', function (): void {
    $mergeConflict = MergeConflict::factory()->create();
    resolve(Features::class)->update(Feature::MergeConflicts, false);

    $this->get(route('commit.show', ['user' => $mergeConflict->commit->user, 'commit' => $mergeConflict->commit]))
        ->assertInertia(fn ($page) => $page->where('mergeConflict', null));
});
