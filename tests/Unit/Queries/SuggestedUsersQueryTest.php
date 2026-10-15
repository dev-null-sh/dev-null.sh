<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;

it('suggests people with commits the user does not follow yet', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    $followed = User::factory()->has(Commit::factory())->create();
    $stranger = User::factory()->has(Commit::factory())->create();

    $user->following()->attach($followed);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions->modelKeys())->toBe([$stranger->id]);
});

it('skips people without approved commits', function (): void {
    $user = User::factory()->create();

    User::factory()->create();
    User::factory()->has(Commit::factory()->pending())->create();
    User::factory()->has(Commit::factory()->rejected())->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toBeEmpty();
});

it('ranks people followed by the user follows first, then by followers', function (): void {
    $user = User::factory()->create();
    [$friendA, $friendB] = User::factory()->count(2)->create();

    $user->following()->attach([$friendA->id, $friendB->id]);

    $popular = User::factory()->has(Commit::factory())->create();
    $followedByOneFriend = User::factory()->has(Commit::factory())->create();
    $followedByBothFriends = User::factory()->has(Commit::factory())->create();
    $quiet = User::factory()->has(Commit::factory())->create();

    $popular->followers()->attach(User::factory()->count(3)->create());
    $followedByOneFriend->followers()->attach($friendA);
    $followedByBothFriends->followers()->attach([$friendA->id, $friendB->id]);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 10);

    expect($suggestions->modelKeys())->toBe([
        $followedByBothFriends->id,
        $followedByOneFriend->id,
        $popular->id,
        $quiet->id,
    ])
        ->and($suggestions->first()->followed_by_following_count)->toBe(2)
        ->and($suggestions->get(2)->followers_count)->toBe(3);
});

it('limits the number of suggestions', function (): void {
    $user = User::factory()->create();

    User::factory()->count(5)->has(Commit::factory())->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toHaveCount(3)
        ->and(resolve(SuggestedUsersQuery::class)->get($user, limit: 2))->toHaveCount(2);
});
