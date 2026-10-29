<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;

it('loads the next commits of the author thread', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();
    $next = Commit::factory()->for($commit, 'parent')->for($author)->create();
    Commit::factory()->for($commit, 'parent')->create();

    $this->getJson(route('commit-continuation.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $next->id)
        ->assertJsonPath('data.0.user.handle', $author->handle)
        ->assertJsonPath('length', 1)
        ->assertJsonPath('next_id', null);
});

it('loads the thread a page at a time', function (): void {
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, CommitContinuationQuery::PER_PAGE + 1) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->for($author)->create();
    }

    $this->getJson(route('commit-continuation.index', $commits[0]))
        ->assertOk()
        ->assertJsonCount(CommitContinuationQuery::PER_PAGE, 'data')
        ->assertJsonPath('next_id', end($commits)->id);
});

it('lets guests load the thread of an approved commit', function (): void {
    $commit = Commit::factory()->create();
    $next = Commit::factory()->for($commit, 'parent')->for($commit->user)->create();

    $this->getJson(route('commit-continuation.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.0.id', $next->id);
});

it('does not load the thread across a block', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->for($commit->user)->create();
    $viewer->blocking()->attach($commit->user);

    $this->actingAs($viewer)
        ->getJson(route('commit-continuation.index', $commit))
        ->assertNotFound();
});

it('does not load the thread of a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-continuation.index', $commit))
        ->assertNotFound();
});
