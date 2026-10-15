<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;

/**
 * @return list<User>
 */
function vouchedFor(User $user, int $count): array
{
    $friend = User::factory()->create();
    $user->following()->attach($friend);

    $vouched = User::factory()->count($count)->has(Commit::factory())->create();
    $friend->following()->attach($vouched);

    return $vouched->all();
}

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

it('ranks people followed by more of the user follows first', function (): void {
    $user = User::factory()->create();
    [$friendA, $friendB] = User::factory()->count(2)->create();

    $user->following()->attach([$friendA->id, $friendB->id]);

    $followedByOneFriend = User::factory()->has(Commit::factory())->create();
    $followedByBothFriends = User::factory()->has(Commit::factory())->create();

    $followedByOneFriend->followers()->attach($friendA);
    $followedByBothFriends->followers()->attach([$friendA->id, $friendB->id]);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions->modelKeys())->toBe([$followedByBothFriends->id, $followedByOneFriend->id])
        ->and($suggestions->first()->followed_by_following_count)->toBe(2);
});

it('mixes popular people in after every two people from the user network', function (): void {
    $user = User::factory()->create();
    $network = vouchedFor($user, 5);

    $popular = User::factory()->count(2)->has(Commit::factory())->create()
        ->each(fn (User $popularUser) => $popularUser->followers()->attach(User::factory()->count(3)->create()));

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 6, popular: 2);

    expect($suggestions)->toHaveCount(6)
        ->and($suggestions->get(2)->followed_by_following_count)->toBe(0)
        ->and($suggestions->get(5)->followed_by_following_count)->toBe(0)
        ->and([$suggestions->get(2)->id, $suggestions->get(5)->id])->toEqualCanonicalizing($popular->modelKeys())
        ->and($suggestions->reject(fn (User $suggested): bool => $suggested->followed_by_following_count === 0)->modelKeys())
        ->each->toBeIn(array_map(fn (User $vouched): string => $vouched->id, $network));
});

it('fills the list with popular people when the user network is small', function (): void {
    $user = User::factory()->create();
    [$vouched] = vouchedFor($user, 1);

    $popular = User::factory()->count(4)->has(Commit::factory())->create();

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 6);

    expect($suggestions)->toHaveCount(5)
        ->and($suggestions->first()->id)->toBe($vouched->id)
        ->and(array_values($suggestions->skip(1)->modelKeys()))->toEqualCanonicalizing($popular->modelKeys());
});

it('fills the list with people from the user network when there are not enough popular people', function (): void {
    $user = User::factory()->create();
    $network = vouchedFor($user, 6);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 6, popular: 2);

    expect($suggestions->modelKeys())->toEqualCanonicalizing(array_map(fn (User $vouched): string => $vouched->id, $network));
});

it('picks popular people from the most followed', function (): void {
    $user = User::factory()->create();

    $mostFollowed = User::factory()->count(20)->has(Commit::factory())->create()
        ->each(fn (User $popularUser) => $popularUser->followers()->attach(User::factory()->create()));
    $unknown = User::factory()->has(Commit::factory())->create();

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 6);

    expect($suggestions)->toHaveCount(6)
        ->and($suggestions->modelKeys())->not->toContain($unknown->id)
        ->each->toBeIn($mostFollowed->modelKeys());
});

it('keeps the same popular picks for a user during the day', function (): void {
    $user = User::factory()->create();

    User::factory()->count(10)->has(Commit::factory())->create();

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($user)->modelKeys())->toBe($query->get($user)->modelKeys());
});

it('limits the number of suggestions', function (): void {
    $user = User::factory()->create();

    User::factory()->count(5)->has(Commit::factory())->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toHaveCount(5)
        ->and(resolve(SuggestedUsersQuery::class)->get($user, limit: 2))->toHaveCount(2);
});
