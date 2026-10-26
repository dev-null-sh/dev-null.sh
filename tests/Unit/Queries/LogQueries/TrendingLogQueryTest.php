<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LogQueries\TrendingLogQuery;

it('includes commits of everyone', function (): void {
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $newer = Commit::factory()->create();

    $query = resolve(TrendingLogQuery::class);

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

    $commits = resolve(TrendingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});

it('excludes deleted commits', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->create()->delete();

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$commit->id]);
});

it('counts replies including deleted ones that still have replies', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->create()->delete();
    $tombstone = Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($tombstone, 'parent')->create();
    $tombstone->delete();

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->sole()->replies_count)->toBe(2);
});

it('leaves out commits by muted people and across blocks', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();

    $user->muting()->attach($muted);
    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);

    $visible = Commit::factory()->create();
    Commit::factory()->for($muted)->create();
    Commit::factory()->for($blocked)->create();
    Commit::factory()->for($blocker)->create();

    $commits = resolve(TrendingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$visible->id]);
});

it('ranks commits that draw stars and replies above newer ones', function (): void {
    $starred = Commit::factory()->create(['created_at' => now()->subHours(1)]);
    $starred->stars()->attach(User::factory()->create());
    $newer = Commit::factory()->create();
    resolve(UpdateCommitHotScores::class)->handle([$starred->id]);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$starred->id, $newer->id]);
});

it('spreads the commits of one author among the commits of others', function (): void {
    $author = User::factory()->create();
    $first = Commit::factory()->for($author)->create();
    $second = Commit::factory()->for($author)->create(['created_at' => now()->subMinute()]);
    $third = Commit::factory()->for($author)->create(['created_at' => now()->subMinutes(2)]);
    $other = Commit::factory()->create(['created_at' => now()->subHour()]);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$first->id, $other->id, $second->id, $third->id]);
});

it('ranks commits of the people the viewer follows above newer commits of others', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed);

    $other = Commit::factory()->create();
    $own = Commit::factory()->for($viewer)->create(['created_at' => now()->subHour()]);
    $followedCommit = Commit::factory()->for($followed)->create(['created_at' => now()->subMinutes(61)]);

    $commits = resolve(TrendingLogQuery::class)->builder($viewer)->get();

    expect($commits->modelKeys())->toBe([$own->id, $followedCommit->id, $other->id]);
});

it('ranks commits by their score alone for a guest', function (): void {
    $newer = Commit::factory()->create();
    $older = Commit::factory()->create(['created_at' => now()->subHour()]);

    $commits = resolve(TrendingLogQuery::class)->builder(null)->get();

    expect($commits->modelKeys())->toBe([$newer->id, $older->id]);
});

it('leaves out commits older than seven days', function (): void {
    $recent = Commit::factory()->create(['created_at' => now()->subDays(7)->addMinute()]);
    Commit::factory()->create(['created_at' => now()->subDays(7)->subMinute()]);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$recent->id]);
});
