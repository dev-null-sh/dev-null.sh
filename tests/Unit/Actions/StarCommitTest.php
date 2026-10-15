<?php

declare(strict_types=1);

use App\Actions\StarCommit;
use App\Models\Commit;
use App\Models\User;

it('may star a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(StarCommit::class)->handle($user, $commit);

    expect($user->hasStarred($commit))->toBeTrue()
        ->and($commit->stars()->count())->toBeOne();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $action = resolve(StarCommit::class);

    $action->handle($user, $commit);
    $action->handle($user, $commit);

    expect($user->starredCommits()->count())->toBeOne();
});

it('may star their own pending commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create();

    resolve(StarCommit::class)->handle($user, $commit);

    expect($user->hasStarred($commit))->toBeTrue();
});
