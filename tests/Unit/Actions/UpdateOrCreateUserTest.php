<?php

declare(strict_types=1);

use App\Actions\UpdateOrCreateUser;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Laravel\Socialite\Two\User as GitHubUser;

it('creates a user from github', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'id' => 583231,
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => ' I am the Octocat. ',
        'location' => 'San Francisco',
        'blog' => 'https://github.blog',
        'company' => '@github',
    ]));

    expect($user->exists)->toBeTrue()
        ->and($user->github_id)->toBe('583231')
        ->and($user->handle)->toBe('octocat')
        ->and($user->name)->toBe('The Octocat')
        ->and($user->email)->toBe('octocat@github.com')
        ->and($user->avatar_url)->toBe('https://avatars.githubusercontent.com/u/583231')
        ->and($user->bio)->toBe('I am the Octocat.')
        ->and($user->location)->toBe('San Francisco')
        ->and($user->website)->toBe('https://github.blog')
        ->and($user->company)->toBe('@github');
});

it('updates an existing user from github', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $existing = User::factory()->create([
        'github_id' => '583231',
        'handle' => 'old-handle',
        'bio' => 'Old bio',
    ]);

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'bio' => 'New bio',
    ]));

    expect($user->is($existing))->toBeTrue()
        ->and(User::query()->count())->toBeOne()
        ->and($existing->refresh()->handle)->toBe('octocat')
        ->and($existing->bio)->toBe('New bio');
});

it('falls back to the handle when the name is blank', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'nickname' => 'octocat',
        'name' => null,
    ]));

    expect($user->name)->toBe('octocat');
});

it('stores blank profile fields as null', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'email' => null,
        'avatar' => '',
        'bio' => '   ',
        'location' => null,
        'blog' => '',
    ]));

    expect($user->email)->toBeNull()
        ->and($user->avatar_url)->toBeNull()
        ->and($user->bio)->toBeNull()
        ->and($user->location)->toBeNull()
        ->and($user->website)->toBeNull()
        ->and($user->company)->toBeNull();
});

it('adds a scheme to websites without one', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'blog' => 'nunomaduro.com',
    ]));

    expect($user->website)->toBe('https://nunomaduro.com');
});

it('keeps http and https websites as they are regardless of scheme case', function (string $website): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'blog' => $website,
    ]));

    expect($user->refresh()->website)->toBe($website);
})->with([
    'http' => 'http://example.com/Path?Key=Value#Section',
    'https' => 'https://example.com/Path?Key=Value#Section',
    'uppercase http' => 'HTTP://example.com/Path?Key=Value#Section',
    'uppercase https' => 'HTTPS://example.com/Path?Key=Value#Section',
    'mixed-case http' => 'HtTp://example.com/Path?Key=Value#Section',
    'mixed-case https' => 'HtTpS://example.com/Path?Key=Value#Section',
]);

it('marks users with a sponsors listing, asking with their own token', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->once()->with('gho_octocat')->andReturnTrue();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'token' => 'gho_octocat',
    ]));

    expect($user->has_sponsors_listing)->toBeTrue()
        ->and($user->github_synced_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('unmarks users without a sponsors listing', function (): void {
    $existing = User::factory()->create([
        'github_id' => '583231',
        'has_sponsors_listing' => true,
    ]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $action->handle(GitHubUser::fake([
        'id' => '583231',
    ]));

    expect($existing->refresh()->has_sponsors_listing)->toBeFalse();
});

it('keeps the last known sponsors listing when github cannot say', function (): void {
    $stale = User::factory()->create([
        'github_id' => '1',
        'handle' => 'octocat',
    ]);

    $existing = User::factory()->create([
        'github_id' => '583231',
        'has_sponsors_listing' => true,
    ]);

    $action = resolve(UpdateOrCreateUser::class);

    $action->handle(GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    expect($existing->refresh())
        ->handle->toBe('octocat')
        ->has_sponsors_listing->toBeTrue()
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());

    expect($stale->refresh()->handle)->toBe('_renamed-1');
});

it('creates users without a sponsors listing when github cannot say', function (): void {
    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake());

    expect($user->refresh()->has_sponsors_listing)->toBeFalse();
});

it('records when the profile was synced from github', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([]));

    expect($user->refresh()->github_synced_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('frees a handle still held by a stale user who renamed on github', function (string $staleUsername): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnTrue();

    $stale = User::factory()->create([
        'github_id' => '1',
        'handle' => $staleUsername,
        'bio' => 'Renamed away',
    ]);

    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    expect($user->handle)->toBe('octocat')
        ->and($user->github_id)->toBe('583231')
        ->and($user->has_sponsors_listing)->toBeTrue()
        ->and($user->is($stale))->toBeFalse()
        ->and(User::query()->count())->toBe(2)
        ->and($stale->refresh()->handle)->toBe('_renamed-1')
        ->and($stale->github_id)->toBe('1')
        ->and($stale->bio)->toBe('Renamed away');
})->with(['octocat', 'OctoCat']);

it('moves an existing bot to its reserved handle when the github owner signs in', function (string $githubId, string $handle, string $fallback): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();
    $bot = User::factory()->create(['github_id' => $githubId, 'handle' => mb_strtolower($handle)]);
    $profile = $bot->only(['id', 'name', 'avatar_url', 'bio', 'onboarded_at', 'remotes_enabled']);
    $post = $bot->commits()->create(['body' => 'An existing bot post.']);
    $friend = User::factory()->create();
    $bot->following()->attach($friend);
    $friend->following()->attach($bot);

    $action = resolve(UpdateOrCreateUser::class);
    $githubUser = GitHubUser::fake(['id' => '583231', 'nickname' => $handle]);
    $owner = $action->handle($githubUser);
    $returning = $action->handle($githubUser);

    expect($owner->handle)->toBe($handle)
        ->and($owner->github_id)->toBe('583231')
        ->and($returning->is($owner))->toBeTrue()
        ->and($owner->is($bot))->toBeFalse()
        ->and($owner->commits()->count())->toBe(0)
        ->and($owner->following()->count())->toBe(0)
        ->and($owner->followers()->count())->toBe(0);
    expect($bot->refresh()->handle)->toBe($fallback)
        ->and($bot->only(array_keys($profile)))->toEqual($profile)
        ->and($bot->commits()->sole()->is($post))->toBeTrue()
        ->and($bot->isFollowing($friend))->toBeTrue()
        ->and($friend->isFollowing($bot))->toBeTrue();
})->with([
    'world' => ['0', 'world', 'devnull-world'],
    'mixed-case world' => ['0', 'WoRlD', 'devnull-world'],
    'tom' => ['tom', 'tom', 'devnull-tom'],
    'mixed-case tom' => ['tom', 'ToM', 'devnull-tom'],
]);
