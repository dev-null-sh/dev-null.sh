<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Support\Facades\Cache;

it('returns an idle result without a discovery snapshot', function (): void {
    $user = User::factory()->create();

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['status'])->toBe('idle')
        ->and($result['users'])->toBeEmpty()
        ->and($result['scanned'])->toBe(0)
        ->and(array_keys($result))->toBe(['status', 'users', 'scanned', 'fetched_at', 'retry_at']);
});

it('matches immutable ids and returns public local profiles with current follow state', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    $followed = User::factory()->create(['github_id' => '123', 'username' => 'renamed-friend', 'name' => 'Zed']);
    $new = User::factory()->create(['github_id' => '456', 'name' => 'Amy']);
    User::factory()->create(['github_id' => '789', 'username' => 'old-friend']);
    $user->following()->attach($followed);

    Cache::put('github-people:'.$user->id, [
        'id' => 'internal-run-id', 'status' => 'ready', 'github_ids' => ['42', '123', '456', '999'],
        'username' => 'current-owner', 'page' => 1, 'scanned' => 4,
        'fetched_at' => now()->toIso8601String(), 'retry_at' => null,
    ], now()->addHour());

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['users']->pluck('id')->all())->toBe([$new->id, $followed->id]);

    $profiles = $result['users']->toArray();

    expect($profiles[0]['is_followed'])->toBeFalse()
        ->and($profiles[1]['is_followed'])->toBeTrue()
        ->and($profiles[0])->not->toHaveKeys(['email', 'github_id', 'remember_token'])
        ->and($result)->not->toHaveKeys(['github_ids', 'id', 'username']);
});

it('keeps discoveries scoped to the requesting user', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $friend = User::factory()->create();

    Cache::put('github-people:'.$other->id, [
        'id' => 'other-run', 'status' => 'ready', 'github_ids' => [$friend->github_id],
        'username' => $other->username, 'page' => 1, 'scanned' => 1, 'fetched_at' => null, 'retry_at' => null,
    ], now()->addHour());

    expect(resolve(GitHubPeopleQuery::class)->get($owner)['users'])->toBeEmpty();
});

it('matches accounts past a database binding chunk without truncating the discovery', function (): void {
    $user = User::factory()->create(['github_id' => '9000']);
    $friend = User::factory()->create(['github_id' => '600']);

    Cache::put('github-people:'.$user->id, [
        'id' => 'large-run', 'status' => 'ready', 'github_ids' => array_map(strval(...), range(1, 600)),
        'username' => $user->username, 'page' => 6, 'scanned' => 600, 'fetched_at' => null, 'retry_at' => null,
    ], now()->addHour());

    expect(resolve(GitHubPeopleQuery::class)->get($user)['users']->pluck('id')->all())->toBe([$friend->id]);
});
