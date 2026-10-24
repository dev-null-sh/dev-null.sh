<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;

it('returns five distinct authors and counts additional authors rather than commits', function (): void {
    $viewer = User::factory()->create();
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $authors = User::factory()->count(7)->create();
    $authors->each(function (User $author): void {
        Commit::factory()->count(2)->for($author)->create();
    });

    Commit::factory()->for($viewer)->create();
    $result = resolve(LatestLogCommitsQuery::class)->authors(Log::Latest, $viewer, $seen);

    expect($result['total'])->toBe(7)->and($result['users'])->toHaveCount(5);
    expect($result['users']->pluck('id')->diff($authors->modelKeys()))->toBeEmpty();
});

it('only shows followed authors in Following and none in Trending', function (): void {
    $viewer = User::factory()->create();
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $followed = User::factory()->create();
    $viewer->following()->attach($followed);
    Commit::factory()->for($followed)->create();
    Commit::factory()->create();
    $query = resolve(LatestLogCommitsQuery::class);
    $result = $query->authors(Log::Following, $viewer, $seen);

    expect($result['total'])->toBe(1)->and($result['users']->pluck('id')->all())->toBe([$followed->id]);
    expect($query->authors(Log::Trending, $viewer, $seen)['total'])->toBe(0);
});

it('excludes hidden authors replies and commits at or before the cursor', function (): void {
    $viewer = User::factory()->create();
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);
    Commit::factory()->for($muted)->create();
    Commit::factory()->for($blocked)->create();
    Commit::factory()->for($blocker)->create();
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();
    Commit::factory()->create()->delete();
    Commit::factory()->create(['parent_id' => $seen->id]);
    Commit::factory()->create(['created_at' => now()->subHours(2)]);
    Commit::factory()->for($viewer)->create();
    $visible = Commit::factory()->create();

    $result = resolve(LatestLogCommitsQuery::class)->authors(Log::Latest, $viewer, $seen);

    expect($result['total'])->toBe(1)
        ->and($result['users']->pluck('id')->all())->toBe([$visible->user_id]);
});

it('previews public authors for guests and returns no authors when none are new', function (): void {
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $query = resolve(LatestLogCommitsQuery::class);

    expect($query->authors(Log::Latest, null, $seen)['total'])->toBe(0)
        ->and($query->authors(Log::Latest, null, $seen)['users'])->toBeEmpty();

    Commit::factory()->pending()->create();
    $visible = Commit::factory()->create();
    $result = $query->authors(Log::Latest, null, $seen);

    expect($result['total'])->toBe(1)
        ->and($result['users']->pluck('id')->all())->toBe([$visible->user_id]);
});
