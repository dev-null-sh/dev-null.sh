<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Actions\DeleteCommitStar;
use App\Models\Commit;
use App\Models\User;
use App\Services\TheAlgorithm;
use Illuminate\Support\Facades\DB;

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

it('does not load a thread for rescoring when no star was removed', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    resolve(DeleteCommitStar::class)->handle($user, $commit);

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(
        fn (string $query): bool => str_contains($query, 'from "commits"')
    ))->toBeEmpty();
});

it('removes the notification about the star', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreateCommitStar::class)->handle($user, $commit);
    resolve(CreateCommitStar::class)->handle($other, $commit);

    resolve(DeleteCommitStar::class)->handle($user, $commit);

    expect($commit->user->notifications()->sole()->actor_id)->toBe($other->id);
});

it('does not spam the author when toggling a star', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    foreach (range(1, 3) as $attempt) {
        resolve(CreateCommitStar::class)->handle($user, $commit);
        resolve(DeleteCommitStar::class)->handle($user, $commit);
    }

    resolve(CreateCommitStar::class)->handle($user, $commit);

    expect($commit->user->notifications()->count())->toBeOne();
});

it('lowers the commit back', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(CreateCommitStar::class)->handle($user, $commit);
    resolve(DeleteCommitStar::class)->handle($user, $commit);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 0), 1e-9);
});
