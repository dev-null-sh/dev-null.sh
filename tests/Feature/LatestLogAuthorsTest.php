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
