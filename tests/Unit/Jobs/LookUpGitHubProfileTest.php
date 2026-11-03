<?php

declare(strict_types=1);

use App\Actions\CreateGitHubProfile;
use App\Contracts\Services\GitHubProvider;
use App\Enums\ProfileType;
use App\Jobs\LookUpGitHubProfile;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use App\Queries\GitHubLookupPauseQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function octocatAccount(array $attributes = []): array
{
    return [
        'login' => 'octocat',
        'id' => 583231,
        'type' => 'User',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'blog' => 'https://github.blog',
        'company' => '@github',
        ...$attributes,
    ];
}

it('gives a GitHub developer an unclaimed profile, with only what their page shows', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->with('octocat')->andReturn(octocatAccount());
    $github->shouldReceive('followerIds')->once()->with('octocat', 10)->andReturn([]);
    Cache::put('github-profiles:looking-up:octocat', true, 30);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    $profile = Profile::query()->sole();

    expect($profile->type)->toBe(ProfileType::User)
        ->and($profile->isUnclaimed())->toBeTrue()
        ->and($profile->github_id)->toBe('583231')
        ->and($profile->handle)->toBe('octocat')
        ->and($profile->name)->toBe('The Octocat')
        ->and($profile->avatar_url)->toBe('https://avatars.githubusercontent.com/u/583231')
        ->and($profile->bio)->toBe('I am the Octocat.')
        ->and($profile->location)->toBeNull()
        ->and($profile->website)->toBeNull()
        ->and($profile->github_synced_at)->not->toBeNull()
        ->and(User::query()->count())->toBe(0)
        ->and(Cache::has('github-profiles:looking-up:octocat'))->toBeFalse();
});

it('makes the members following a developer on GitHub wait to follow them here', function (): void {
    $follower = User::factory()->create();
    User::factory()->create();
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->andReturn(octocatAccount());
    $github->shouldReceive('followerIds')->once()->with('octocat', 10)->andReturn([$follower->github_id, '424242']);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    expect(DB::table('pending_follows')->get(['user_id', 'github_id'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([['user_id' => $follower->id, 'github_id' => '583231']]);
});

it('gives a GitHub organization its page, followed by those waiting for it', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->with('laravel')->andReturn(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);
    $follower = User::factory()->create();
    DB::table('pending_follows')->insert(['user_id' => $follower->id, 'github_id' => '958072', 'created_at' => now()]);

    dispatch_sync(new LookUpGitHubProfile('laravel'));

    $organization = Organization::query()->sole();

    expect($organization->handle)->toBe('laravel')
        ->and($follower->followedOrganizations()->pluck('organizations.id')->all())->toBe([$organization->id]);
});

it('remembers for a day that no GitHub account has the handle', function (?array $account): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->andReturn($account);

    dispatch_sync(new LookUpGitHubProfile('nobody'));

    expect(Cache::get('github-profiles:not-found:nobody'))->toBe('missing')
        ->and(Profile::query()->count())->toBe(0);

    $this->travel(1)->day();

    expect(Cache::has('github-profiles:not-found:nobody'))->toBeFalse();
})->with([
    'no account' => [null],
    'a bot' => [['login' => 'nobody', 'id' => 1, 'type' => 'Bot']],
]);

it('never brings back the page of someone who deleted their account', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->andReturn(octocatAccount());
    DB::table('deleted_github_accounts')->insert(['github_id' => '583231']);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    expect(Cache::get('github-profiles:not-found:octocat'))->toBe('deleted')
        ->and(Profile::query()->count())->toBe(0);
});

it('moves the unclaimed profile of a developer who renamed, and drops the one whose handle someone else took', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->with('octocat')->andReturn(octocatAccount());
    $github->shouldNotReceive('followerIds');
    $renamed = Profile::factory()->create(['github_id' => '583231', 'handle' => 'octo-old']);
    $previous = Profile::factory()->create(['github_id' => '1', 'handle' => 'octocat']);
    $member = User::factory()->create(['handle' => 'octocat-member']);
    DB::table('pending_follows')->insert(['user_id' => $member->id, 'github_id' => '1', 'created_at' => now()]);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    expect($renamed->refresh()->handle)->toBe('octocat')
        ->and($member->profile->refresh()->handle)->toBe('octocat-member');
    $this->assertModelMissing($previous);

    expect(DB::table('pending_follows')->pluck('github_id')->all())->toBe(['1']);
});

it('only moves a user who renamed on GitHub to their new handle, leaving the rest to their next sign-in', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->with('octocat')->andReturn(octocatAccount());
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'octo-old', 'name' => 'Old name', 'github_synced_at' => now()->subWeek()]);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    $profile = $user->profile->refresh();

    expect($profile->handle)->toBe('octocat')
        ->and($profile->name)->toBe('Old name')
        ->and($profile->github_synced_at?->isBefore(now()->subDay()))->toBeTrue()
        ->and(Profile::query()->count())->toBe(1);
});

it('leaves the profile of a user who renamed away under a placeholder, as it was', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->with('octocat')->andReturn(octocatAccount());
    $github->shouldReceive('followerIds')->once()->andReturn([]);
    $member = User::factory()->create(['github_id' => '1', 'handle' => 'octocat']);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    expect($member->profile->refresh()->handle)->toBe('_renamed-1')
        ->and(Profile::query()->where('handle', 'octocat')->sole()->isUnclaimed())->toBeTrue();
});

it('asks GitHub nothing while lookups wait for the rate limit to reset', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('accountByLogin');
    Cache::put('github-organizations:paused', now()->addMinutes(30)->getTimestamp(), now()->addMinutes(30));
    Cache::put('github-profiles:looking-up:octocat', true, 30);

    dispatch_sync(new LookUpGitHubProfile('octocat'));

    expect(Cache::has('github-profiles:looking-up:octocat'))->toBeFalse()
        ->and(Profile::query()->count())->toBe(0);
});

it('stops showing the lookup pending when GitHub fails', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->once()->andThrow(new ConnectionException('GitHub is down.'));
    Cache::put('github-profiles:looking-up:octocat', true, 30);

    expect(fn () => new LookUpGitHubProfile('octocat')->handle(resolve(GitHubLookupPauseQuery::class), resolve(CreateGitHubProfile::class)))
        ->toThrow(ConnectionException::class)
        ->and(Cache::has('github-profiles:looking-up:octocat'))->toBeFalse();
});

it('looks a handle up once at a time', function (): void {
    expect(new LookUpGitHubProfile('octocat')->uniqueId())->toBe('octocat');
});
