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
