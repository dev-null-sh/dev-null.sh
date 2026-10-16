<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Support\Facades\Cache;

it('returns an idle result without a discovery snapshot', function (): void {
    $user = User::factory()->create();
    $this->expectsDatabaseQueryCount(0);

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['status'])->toBe('idle')
        ->and($result['users'])->toBeEmpty()
        ->and($result['scanned'])->toBe(0)
        ->and($result['truncated'])->toBeFalse()
        ->and(array_keys($result))->toBe(['status', 'users', 'scanned', 'fetched_at', 'retry_at', 'truncated']);
});

it('returns only progress for incomplete discoveries without querying local profiles', function (string $status, ?string $retryAt): void {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Cache::put('github-people:'.$user->id, [
        'id' => 'partial-run', 'status' => $status, 'github_ids' => [$friend->github_id],
        'username' => $user->username, 'page' => 1, 'scanned' => 1,
        'fetched_at' => null, 'retry_at' => $retryAt,
    ], now()->addHour());
    $this->expectsDatabaseQueryCount(0);

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['status'])->toBe($status)
        ->and($result['users'])->toBeEmpty()
        ->and($result['scanned'])->toBe(1)
        ->and($result['fetched_at'])->toBeNull()
        ->and($result['retry_at'])->toBe($retryAt);
})->with([
    'loading' => ['loading', null],
    'unavailable' => ['unavailable', '2026-10-16T10:00:00+00:00'],
    'rate limited' => ['rate_limited', '2026-10-16T11:00:00+00:00'],
    'renamed' => ['renamed', '2026-10-16T12:00:00+00:00'],
]);

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
        'pages_requested' => 6, 'restart_count' => 1, 'truncated' => true,
    ], now()->addHour());

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['users']->pluck('id')->all())->toBe([$new->id, $followed->id]);

    $profiles = $result['users']->toArray();

    expect($profiles[0]['is_followed'])->toBeFalse()
        ->and($profiles[1]['is_followed'])->toBeTrue()
        ->and($profiles[0])->not->toHaveKeys(['email', 'github_id', 'remember_token'])
        ->and($result['truncated'])->toBeTrue()
        ->and($result)->not->toHaveKeys(['github_ids', 'id', 'username', 'pages_requested', 'restart_count']);
});

it('keeps discoveries scoped to the requesting user', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $friend = User::factory()->create();

    Cache::put('github-people:'.$other->id, [
        'id' => 'other-run', 'status' => 'ready', 'github_ids' => [$friend->github_id],
        'username' => $other->username, 'page' => 1, 'scanned' => 1, 'fetched_at' => null, 'retry_at' => null,
        'truncated' => true,
    ], now()->addHour());

    $result = resolve(GitHubPeopleQuery::class)->get($owner);

    expect($result['users'])->toBeEmpty()
        ->and($result['truncated'])->toBeFalse();
});

it('excludes discovered accounts blocked in either direction', function (bool $outgoing): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    $visible = User::factory()->create();
    [$blocker, $blocked] = $outgoing ? [$user, $person] : [$person, $user];
    $blocker->blocking()->attach($blocked);

    Cache::put('github-people:'.$user->id, [
        'id' => 'completed-run', 'status' => 'ready', 'github_ids' => [$person->github_id, $visible->github_id],
        'username' => $user->username, 'page' => 1, 'scanned' => 2,
        'fetched_at' => now()->toIso8601String(), 'retry_at' => null,
    ], now()->addHour());

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['users']->pluck('id')->all())->toBe([$visible->id]);
})->with(['blocked by viewer' => true, 'blocked by discovered person' => false]);

it('matches accounts past a database binding chunk without truncating the discovery', function (): void {
    $user = User::factory()->create(['github_id' => '9000']);
    $friend = User::factory()->create(['github_id' => '600']);

    Cache::put('github-people:'.$user->id, [
        'id' => 'large-run', 'status' => 'ready', 'github_ids' => array_map(strval(...), range(1, 600)),
        'username' => $user->username, 'page' => 6, 'scanned' => 600, 'fetched_at' => null, 'retry_at' => null,
    ], now()->addHour());

    $result = resolve(GitHubPeopleQuery::class)->get($user);

    expect($result['users']->pluck('id')->all())->toBe([$friend->id])
        ->and($result['truncated'])->toBeFalse();
});
