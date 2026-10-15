<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\LogQueries\EveryoneLogQuery;

it('includes commits of everyone newest first', function (): void {
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $newer = Commit::factory()->create();

    $query = resolve(EveryoneLogQuery::class);

    $commits = $query->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$newer->id, $older->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->relationLoaded('linkPreview'))->toBeTrue();
});

it('only includes approved commits of others', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create(['created_at' => now()->subMinutes(2)]);
    $ownPending = Commit::factory()->for($user)->pending()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();

    $commits = resolve(EveryoneLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});

it('excludes deleted commits', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->create()->delete();

    $commits = resolve(EveryoneLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$commit->id]);
});

it('counts replies including deleted ones that still have replies', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->create()->delete();
    $tombstone = Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($tombstone, 'parent')->create();
    $tombstone->delete();

    $commits = resolve(EveryoneLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->sole()->replies_count)->toBe(2);
});
