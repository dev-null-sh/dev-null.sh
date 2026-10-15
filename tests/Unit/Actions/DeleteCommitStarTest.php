<?php

declare(strict_types=1);

use App\Actions\DeleteCommitStar;
use App\Models\Commit;
use App\Models\User;

it('may unstar a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $user->starredCommits()->attach($commit);

    resolve(DeleteCommitStar::class)->handle($user, $commit);

    expect($user->hasStarred($commit))->toBeFalse();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(DeleteCommitStar::class)->handle($user, $commit);

    expect($user->starredCommits()->count())->toBe(0);
});
