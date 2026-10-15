<?php

declare(strict_types=1);

use App\Actions\UpdateOrCreateUser;
use App\Models\User;
use Laravel\Socialite\Two\User as GitHubUser;

it('creates a user from github', function (): void {
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
        ->and($user->username)->toBe('octocat')
        ->and($user->name)->toBe('The Octocat')
        ->and($user->email)->toBe('octocat@github.com')
        ->and($user->avatar_url)->toBe('https://avatars.githubusercontent.com/u/583231')
        ->and($user->bio)->toBe('I am the Octocat.')
        ->and($user->location)->toBe('San Francisco')
        ->and($user->website)->toBe('https://github.blog')
        ->and($user->company)->toBe('@github');
});

it('updates an existing user from github', function (): void {
    $existing = User::factory()->create([
        'github_id' => '583231',
        'username' => 'old-username',
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
        ->and($existing->refresh()->username)->toBe('octocat')
        ->and($existing->bio)->toBe('New bio');
});

it('falls back to the username when the name is blank', function (): void {
    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'nickname' => 'octocat',
        'name' => null,
    ]));

    expect($user->name)->toBe('octocat');
});

it('stores blank profile fields as null', function (): void {
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
    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'blog' => 'nunomaduro.com',
    ]));

    expect($user->website)->toBe('https://nunomaduro.com');
});

it('keeps http websites as they are', function (): void {
    $action = resolve(UpdateOrCreateUser::class);

    $user = $action->handle(GitHubUser::fake([
        'blog' => 'http://example.com',
    ]));

    expect($user->website)->toBe('http://example.com');
});
