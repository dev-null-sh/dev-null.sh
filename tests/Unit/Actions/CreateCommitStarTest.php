<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Models\Commit;
use App\Models\User;
use App\Services\TheAlgorithm;

it('may star a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreateCommitStar::class)->handle($user, $commit);

    expect($user->hasStarred($commit))->toBeTrue()
        ->and($commit->stars()->count())->toBeOne();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $action = resolve(CreateCommitStar::class);

    $action->handle($user, $commit);
    $action->handle($user, $commit);

    expect($user->starredCommits()->count())->toBeOne();
});

it('notifies the author', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreateCommitStar::class)->handle($user, $commit);

    expect($commit->user->notifications()->sole()->actor_id)->toBe($user->id);
});

it('lifts the commit', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitStar::class)->handle(User::factory()->create(), $commit);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 1, 0), 1e-9);
});
