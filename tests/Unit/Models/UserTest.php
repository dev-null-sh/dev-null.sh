<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'username',
            'name',
            'avatar_url',
            'bio',
            'location',
            'website',
            'company',
            'created_at',
            'updated_at',
            'lane',
        ]);
});

it('has commits', function (): void {
    $user = User::factory()->create();
    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->create();

    expect($user->commits)->toHaveCount(2);
});

it('has following and followers', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $follower = User::factory()->create();

    $user->following()->attach($followed);
    $user->followers()->attach($follower);

    expect($user->following->modelKeys())->toBe([$followed->id])
        ->and($user->followers->modelKeys())->toBe([$follower->id])
        ->and($followed->followers->modelKeys())->toBe([$user->id]);
});

it('loads commit and follow counts', function (): void {
    $user = User::factory()->has(Commit::factory()->count(2))->create();
    $user->following()->attach(User::factory()->count(3)->create());
    User::factory()->create()->following()->attach($user);
    Commit::factory()->create();

    $user->loadCounts();

    expect($user->commits_count)->toBe(2)
        ->and($user->following_count)->toBe(3)
        ->and($user->followers_count)->toBe(1)
        ->and($user->isDirty())->toBeFalse();
});

it('loads commit counts visible to the viewer', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    expect($user->loadCounts(User::factory()->create())->commits_count)->toBe(1)
        ->and($user->loadCounts()->commits_count)->toBe(3);
});

it('does not count deleted commits', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    Commit::factory()->for($user)->create()->delete();

    expect($user->loadCounts()->commits_count)->toBe(1)
        ->and($user->loadPublicCounts()->commits_count)->toBe(1);
});

it('loads public counts with approved commits only', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();
    $user->following()->attach(User::factory()->count(2)->create());
    User::factory()->create()->following()->attach($user);

    $user->loadPublicCounts();

    expect($user->commits_count)->toBe(1)
        ->and($user->following_count)->toBe(2)
        ->and($user->followers_count)->toBe(1)
        ->and($user->isDirty())->toBeFalse();
});

it('loads counts without joining users', function (): void {
    $user = User::factory()->create();

    DB::enableQueryLog();

    $user->loadCounts();

    $queries = array_column(DB::getQueryLog(), 'query');

    expect($queries)->toHaveCount(3)
        ->each->not->toContain('join');
});

it('knows if it is following a user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($user->isFollowing($other))->toBeFalse();

    $user->following()->attach($other);

    expect($user->isFollowing($other))->toBeTrue()
        ->and($other->isFollowing($user))->toBeFalse();
});

it('has no password', function (): void {
    $user = User::factory()->create();

    expect($user->getAuthPassword())->toBe('');
});

it('knows if it is an admin', function (): void {
    config(['app.admins' => ['NunoMaduro']]);

    expect(User::factory()->create(['username' => 'nunomaduro'])->isAdmin())->toBeTrue()
        ->and(User::factory()->create(['username' => 'octocat'])->isAdmin())->toBeFalse();
});

it('has the same lane as the frontend', function (string $id, int $lane): void {
    expect(User::factory()->make(['id' => $id])->lane())->toBe($lane);
})->with([
    ['ff6e0733-5f5a-4d58-a321-4da554c0d799', 0],
    ['5be701c9-deb7-4948-b80a-a1e0d1f0fee9', 1],
    ['edfd235d-6874-4a82-a1ba-4f40c5f40510', 2],
    ['3b4571bc-5802-4005-afc1-2d9aa826b5d3', 3],
    ['3a788e93-4cf7-4819-9377-94be2f329563', 4],
    ['bb5da83f-f9cf-46f2-b66e-51908e44dd14', 5],
]);

it('uses the chosen lane over the automatic one', function (): void {
    $user = User::factory()->make(['id' => 'ff6e0733-5f5a-4d58-a321-4da554c0d799', 'preferred_lane' => 4]);

    expect($user->automaticLane())->toBe(0)
        ->and($user->lane())->toBe(4);
});

it('serializes the effective lane', function (?int $preferredLane, int $lane): void {
    $user = User::factory()->create(['id' => '3b4571bc-5802-4005-afc1-2d9aa826b5d3', 'preferred_lane' => $preferredLane])->refresh();

    expect($user->toArray())
        ->toHaveKey('lane', $lane)
        ->not->toHaveKey('preferred_lane');
})->with([
    'automatic' => [null, 3],
    'chosen' => [0, 0],
]);
