<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;

it('counts every newer commit while excluding the viewer', function (): void {
    $viewer = User::factory()->create();
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $authors = User::factory()->count(7)->create();
    $authors->each(function (User $author): void {
        Commit::factory()->count(2)->for($author)->create();
    });

    Commit::factory()->for($viewer)->create();
    expect(resolve(LatestLogCommitsQuery::class)->count(Log::Latest, $viewer, $seen))->toBe(14);
});

it('only counts followed authors in Following and none in Trending', function (): void {
    $viewer = User::factory()->create();
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $followed = User::factory()->create();
    $viewer->following()->attach($followed);
    Commit::factory()->for($followed)->create();
    Commit::factory()->create();
    $query = resolve(LatestLogCommitsQuery::class);
    expect($query->count(Log::Following, $viewer, $seen))->toBe(1);
    expect($query->count(Log::Trending, $viewer, $seen))->toBe(0);
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
    Commit::factory()->create();

    expect(resolve(LatestLogCommitsQuery::class)->count(Log::Latest, $viewer, $seen))->toBe(1);
});

it('counts public commits for guests and returns zero when none are new', function (): void {
    $seen = Commit::factory()->create(['created_at' => now()->subHour()]);
    $query = resolve(LatestLogCommitsQuery::class);

    expect($query->count(Log::Latest, null, $seen))->toBe(0);

    Commit::factory()->pending()->create();
    Commit::factory()->create();
    expect($query->count(Log::Latest, null, $seen))->toBe(1);
});

it('returns only requested identifiers no longer visible in the log', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed);
    $visible = Commit::factory()->for($followed)->create();
    $deleted = Commit::factory()->for($followed)->create();
    $deleted->delete();

    $stranger = Commit::factory()->create();
    $muted = User::factory()->create();
    $viewer->muting()->attach($muted);
    $hidden = Commit::factory()->for($muted)->create();
    $reply = Commit::factory()->for($followed)->create(['parent_id' => $visible->id]);
    $query = resolve(LatestLogCommitsQuery::class);
    $ids = [$visible->id, $deleted->id, $stranger->id, $hidden->id, $reply->id];
    expect($query->missing(Log::Latest, $viewer, $ids))->toBe([$deleted->id, $hidden->id, $reply->id]);
    expect($query->missing(Log::Following, $viewer, $ids))->toBe([$deleted->id, $stranger->id, $hidden->id, $reply->id]);
    expect($query->missing(Log::Latest, null, [$visible->id, $deleted->id]))->toBe([$deleted->id]);
    expect($query->missing(Log::Latest, $viewer, []))->toBe([]);
    expect($query->missing(Log::Trending, $viewer, $ids))->toBe([]);
});
