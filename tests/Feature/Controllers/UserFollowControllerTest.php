<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\UserAchievementsQuery;

it('may follow a user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($follower)
        ->fromRoute('user.show', $user)
        ->post(route('user-follow.store', $user));

    $response->assertRedirectToRoute('user.show', $user);

    expect($follower->isFollowing($user))->toBeTrue();
});

it('may not follow themselves', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user.show', $user)
        ->post(route('user-follow.store', $user));

    $response->assertForbidden();

    expect($user->following()->count())->toBe(0);
});

it('may unfollow a user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $follower->following()->attach($user);

    $response = $this->actingAs($follower)
        ->fromRoute('user.show', $user)
        ->delete(route('user-follow.destroy', $user));

    $response->assertRedirectToRoute('user.show', $user);

    expect($follower->isFollowing($user))->toBeFalse();
});

it('requires authentication to follow', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('user-follow.store', $user));

    $response->assertRedirectToRoute('home');

    expect($user->followers()->count())->toBe(0);
});

it('applies the luddite penalty once while Tom is unfollowed and restores xp on refollow', function (int $commits, int $initialXp, int $penalizedXp, int $level, int $progress): void {
    $user = User::factory()->create();
    resolve(CreateTomFriendship::class)->handle($user);
    $tom = User::query()->where('github_id', CreateTomFriendship::GITHUB_ID)->firstOrFail();
    Commit::factory()->for($user)->count($commits)->create();
    $query = resolve(UserAchievementsQuery::class);

    $initial = $query->get($user, null);
    expect($initial['xp'])->toBe($initialXp)
        ->and(collect($initial['badges'])->firstWhere('id', 'luddite')['unlocked'])->toBeFalse();

    $this->actingAs($user)->delete(route('user-follow.destroy', $tom))->assertRedirect();
    $this->delete(route('user-follow.destroy', $tom))->assertRedirect();

    $result = $query->get($user, null);
    expect(collect($result['badges'])->firstWhere('id', 'luddite'))->toMatchArray([
        'unlocked' => true,
        'progress' => 1,
        'target' => 1,
    ])->and($result['xp_breakdown']['luddite'])->toBe(['count' => 1, 'multiplier' => -100, 'xp' => -100])
        ->and($result['xp'])->toBe($penalizedXp)
        ->and($result['level'])->toBe($level)
        ->and($result['level_progress'])->toBe($progress);

    $this->post(route('user-follow.store', $tom))->assertRedirect();

    $restored = $query->get($user, null);
    expect($restored['xp'])->toBe($initialXp)
        ->and($restored['xp_breakdown']['luddite']['xp'])->toBe(0)
        ->and(collect($restored['badges'])->firstWhere('id', 'luddite')['unlocked'])->toBeFalse();
})->with([
    'rank drops from operator to daemon' => [14, 155, 55, 2, 5],
    'xp cannot become negative' => [1, 25, 0, 1, 0],
]);
