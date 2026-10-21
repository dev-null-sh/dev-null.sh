<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Enums\LivePlatform;
use App\Enums\Log;
use App\Http\Middleware\HandleInertiaRequests;
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

it('follows from an unrelated log without replacing its loaded commits or ranking', function (Log $log): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    Commit::factory()->for($author)->create();
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
    ];
    $this->actingAs($viewer)->get(route('log.show', $log), $headers)->assertOk();
    $seed = session('trending_seed');

    $this->withHeaders([
        ...$headers,
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'auth.user,liveFollowing',
    ])->fromRoute('log.show', $log)->followingRedirects()
        ->post(route('user-follow.store', $author))
        ->assertOk()
        ->assertJsonPath('props.auth.user.following_count', 1)
        ->assertJsonMissingPath('props.commits')
        ->assertJsonMissingPath('props.suggestions');

    expect($viewer->isFollowing($author))->toBeTrue()
        ->and(session('trending_seed'))->toBe($seed);
})->with([Log::Latest, Log::Trending]);

it('replaces the empty Following log with commits after its first follow', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();

    $this->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'auth.user,liveFollowing,commits,suggestions,newCommitsCount',
        'X-Inertia-Reset' => 'commits',
    ])->fromRoute('log.show', Log::Following)->followingRedirects()
        ->post(route('user-follow.store', $author))
        ->assertOk()
        ->assertJsonPath('props.auth.user.following_count', 1)
        ->assertJsonCount(1, 'props.commits.data')
        ->assertJsonPath('props.commits.data.0.id', $commit->id)
        ->assertJsonPath('props.suggestions', [])
        ->assertJsonPath('props.newCommitsCount', 0)
        ->assertJsonPath('scrollProps.commits.reset', true)
        ->assertJsonMissingPath('mergeProps');

    expect($viewer->isFollowing($author))->toBeTrue();
});

it('removes unfollowed commits and restores suggestions after the last unfollow', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author);
    Commit::factory()->for($author)->create();

    $this->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'auth.user,liveFollowing,commits,suggestions,newCommitsCount',
        'X-Inertia-Reset' => 'commits',
    ])->fromRoute('log.show', Log::Following)->followingRedirects()
        ->delete(route('user-follow.destroy', $author))
        ->assertOk()
        ->assertJsonPath('props.auth.user.following_count', 0)
        ->assertJsonPath('props.commits.data', [])
        ->assertJsonCount(1, 'props.suggestions')
        ->assertJsonPath('props.suggestions.0.user.id', $author->id)
        ->assertJsonPath('scrollProps.commits.reset', true)
        ->assertJsonMissingPath('mergeProps');

    expect($viewer->isFollowing($author))->toBeFalse();
});

it('updates live-following sidebar membership with silent log follow responses', function (Log $log): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHour(),
    ]);
    $this->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'auth.user,liveFollowing',
    ])->fromRoute('log.show', $log)->followingRedirects()
        ->post(route('user-follow.store', $author))
        ->assertOk()
        ->assertJsonPath('props.liveFollowing.0.id', $author->id)
        ->assertJsonMissingPath('props.commits');

    $this->fromRoute('log.show', $log)->followingRedirects()->delete(route('user-follow.destroy', $author))
        ->assertOk()
        ->assertJsonPath('props.liveFollowing', [])
        ->assertJsonPath('props.auth.user.following_count', 0)
        ->assertJsonMissingPath('props.commits');
})->with([Log::Latest, Log::Trending, Log::Following]);
