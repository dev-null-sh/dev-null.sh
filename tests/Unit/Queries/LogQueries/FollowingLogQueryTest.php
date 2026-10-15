<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\LogQueries\FollowingLogQuery;
use Illuminate\Support\Facades\DB;

it('includes own commits and commits of followed users newest first', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    $own = Commit::factory()->for($user)->create(['created_at' => now()->subMinute()]);
    $followedCommit = Commit::factory()->for($followed)->create();
    Commit::factory()->create();

    $query = resolve(FollowingLogQuery::class);

    $commits = $query->builder($user)->get();

    expect($commits->modelKeys())->toBe([$followedCommit->id, $own->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->relationLoaded('linkPreview'))->toBeTrue();
});

it('orders commits by creation date rather than insertion order', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    $newer = Commit::factory()->for($user)->create();
    $older = Commit::factory()->for($followed)->create(['created_at' => now()->subMinute()]);

    $commits = resolve(FollowingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$newer->id, $older->id]);
});

it('resolves followed users in a subquery instead of loading their ids', function (): void {
    $user = User::factory()->create();

    $user->following()->attach(User::factory()->count(3)->create());

    DB::enableQueryLog();

    $builder = resolve(FollowingLogQuery::class)->builder($user);

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($builder->toSql())->toContain('select "followed_id" from "follows"');
});

it('only includes approved commits of followed users', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    $approved = Commit::factory()->for($followed)->create(['created_at' => now()->subMinutes(2)]);
    $ownPending = Commit::factory()->for($user)->pending()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($followed)->pending()->create();
    Commit::factory()->for($followed)->rejected()->create();

    $commits = resolve(FollowingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});

it('excludes deleted commits', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create()->delete();

    $commits = resolve(FollowingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$commit->id]);
});
