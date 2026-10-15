<?php

declare(strict_types=1);

use App\Actions\UnstarCommit;
use App\Models\Commit;
use App\Models\User;

it('may unstar a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $user->starredCommits()->attach($commit);

    resolve(UnstarCommit::class)->handle($user, $commit);

    expect($user->hasStarred($commit))->toBeFalse();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(UnstarCommit::class)->handle($user, $commit);

    expect($user->starredCommits()->count())->toBe(0);
});
