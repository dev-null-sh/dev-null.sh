<?php

declare(strict_types=1);

use App\Actions\StarCommit;
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

it('removes the notification about the star', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(StarCommit::class)->handle($user, $commit);
    resolve(StarCommit::class)->handle($other, $commit);

    resolve(UnstarCommit::class)->handle($user, $commit);

    expect($commit->user->notifications()->sole()->actor_id)->toBe($other->id);
});

it('does not spam the author when toggling a star', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    foreach (range(1, 3) as $attempt) {
        resolve(StarCommit::class)->handle($user, $commit);
        resolve(UnstarCommit::class)->handle($user, $commit);
    }

    resolve(StarCommit::class)->handle($user, $commit);

    expect($commit->user->notifications()->count())->toBeOne();
});
