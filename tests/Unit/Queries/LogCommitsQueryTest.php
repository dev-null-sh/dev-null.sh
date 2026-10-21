<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LogCommitsQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('keeps every loaded Following commit when a newly followed author adds commits in that range', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $newFollow = User::factory()->create();
    $viewer->following()->attach([$followed->id, $newFollow->id]);
    $loaded = Commit::factory()->for($followed)->count(45)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();
    $added = Commit::factory()->for($newFollow)->count(10)->create();
    $through = $loaded[39];

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $viewer, $through);

    expect($page->getCollection()->modelKeys())->toHaveCount(50)
        ->toEqualCanonicalizing([...$loaded->take(40)->modelKeys(), ...$added->modelKeys()])
        ->and($page->nextCursor()?->parameter('id'))->toBe($through->id);
});

it('keeps unrelated loaded rows and the cursor when unfollowing the author of the range boundary', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $unfollowed = User::factory()->create();
    $viewer->following()->attach($followed);
    $loaded = Commit::factory()->for($followed)->count(45)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index * 2)],
    )->create();
    $through = Commit::factory()->for($unfollowed)->create(['created_at' => now()->subMinutes(59)]);

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $viewer, $through);

    expect($page->getCollection()->modelKeys())->toEqual($loaded->take(30)->modelKeys())
        ->and($page->nextCursor()?->parameter('id'))->toBe($loaded[29]->id);
});

it('uses time-ordered ids to retain the exact boundary within the same second', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(30)->create();
    $through = $loaded[4];

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $viewer, $through);

    expect($page->getCollection()->modelKeys())->toEqual($loaded->skip(4)->reverse()->values()->modelKeys())
        ->and($page->nextCursor()?->parameter('id'))->toBe($through->id);
});

it('keeps the ordinary page size when the retained range has fewer than twenty commits', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(25)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $viewer, $loaded[2]);

    expect($page->getCollection()->modelKeys())->toEqual($loaded->take(20)->modelKeys())
        ->and($page->nextCursor()?->parameter('id'))->toBe($loaded[19]->id);
});

it('keeps other logs at their normal page size even when a range is supplied', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(30)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Latest, $viewer, $loaded[29]);

    expect($page->getCollection()->modelKeys())->toEqual($loaded->take(20)->modelKeys());
});

it('retains the boundary when a newer commit arrives between range retrieval and pagination metadata', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(25)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();
    $inserted = false;
    DB::listen(function (QueryExecuted $query) use ($viewer, &$inserted): void {
        if (! $inserted && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "commits"')) {
            $inserted = true;
            Commit::factory()->for($viewer)->create();
        }
    });

    $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $viewer, $loaded[19]);

    expect($inserted)->toBeTrue()
        ->and($page->getCollection()->modelKeys())->toContain(...$loaded->take(20)->modelKeys())
        ->and($page->nextCursor()?->parameter('id'))->toBe($loaded[19]->id);
});
