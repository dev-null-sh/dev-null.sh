<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\FollowingFeedQuery;

it('includes only commits of followed users newest first', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    $older = Commit::factory()->for($followed)->create(['created_at' => now()->subMinute()]);
    $newer = Commit::factory()->for($followed)->create();
    Commit::factory()->for($user)->create();
    Commit::factory()->create();

    $commits = resolve(FollowingFeedQuery::class)->get($user);

    expect($commits->modelKeys())->toBe([$newer->id, $older->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue();
});

it('excludes commits of followers the user does not follow', function (): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();
    $user->followers()->attach($follower);

    Commit::factory()->for($follower)->create();

    expect(resolve(FollowingFeedQuery::class)->get($user))->toBeEmpty();
});

it('only includes approved top-level commits that are not deleted', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    $approved = Commit::factory()->for($followed)->create();
    Commit::factory()->for($followed)->pending()->create();
    Commit::factory()->for($followed)->rejected()->create();
    Commit::factory()->for($followed)->create()->delete();
    Commit::factory()->for($followed)->create(['parent_id' => $approved->id]);

    expect(resolve(FollowingFeedQuery::class)->get($user)->modelKeys())->toBe([$approved->id]);
});

it('limits the number of commits', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    Commit::factory()->for($followed)->count(3)->create();

    expect(resolve(FollowingFeedQuery::class)->get($user, 2))->toHaveCount(2)
        ->and(resolve(FollowingFeedQuery::class)->get($user))->toHaveCount(3);
});
