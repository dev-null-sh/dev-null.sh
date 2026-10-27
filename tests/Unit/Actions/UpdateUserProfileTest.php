<?php

declare(strict_types=1);

use App\Actions\UpdateUserProfile;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

it('mirrors the github profile of the user', function (): void {
    $user = User::factory()->create([
        'github_id' => '583231',
        'handle' => 'old-handle',
        'email' => 'private@example.com',
        'has_sponsors_listing' => true,
    ]);

    fakeGitHubAccount($user, [
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => null,
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        'bio' => ' I am the Octocat. ',
        'company' => '@github',
        'location' => 'San Francisco',
        'blog' => 'github.blog',
    ]);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeTrue()
        ->and($user->refresh())
        ->handle->toBe('octocat')
        ->name->toBe('The Octocat')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231?v=4')
        ->bio->toBe('I am the Octocat.')
        ->company->toBe('@github')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->email->toBe('private@example.com')
        ->has_sponsors_listing->toBeTrue()
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

it('clears profile fields removed on github', function (): void {
    $user = User::factory()->create();

    fakeGitHubAccount($user, [
        'name' => null,
        'avatar_url' => '',
        'bio' => null,
        'company' => null,
        'location' => '  ',
        'blog' => '',
    ]);

    resolve(UpdateUserProfile::class)->handle($user);

    expect($user->refresh())
        ->name->toBe('octocat')
        ->avatar_url->toBeNull()
        ->bio->toBeNull()
        ->company->toBeNull()
        ->location->toBeNull()
        ->website->toBeNull();
});

it('mirrors the ssh keys of the user', function (): void {
    $user = User::factory()->create(['github_id' => '583231']);
    $removed = SshKey::factory()->for($user)->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    Http::fake([
        'api.github.com/user/583231' => Http::response(['id' => 583231, 'login' => 'octocat']),
    ]);
    fakeGitHubSshKeys($user, [$publicKey], handle: 'octocat');

    resolve(UpdateUserProfile::class)->handle($user);

    expect($removed->fresh())->toBeNull()
        ->and($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});

it('looks the account up by its github id, so a renamed handle cannot be claimed', function (): void {
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);

    fakeGitHubAccount($user, ['login' => 'octocat']);

    resolve(UpdateUserProfile::class)->handle($user);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/user/583231');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'old-handle'));
});

it('leaves the profile and ssh keys untouched when github fails', function (int $status): void {
    $user = User::factory()->create(['handle' => 'old-handle', 'bio' => 'Old bio']);
    $sshKey = SshKey::factory()->for($user)->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Error'], $status),
    ]);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh())
        ->handle->toBe('old-handle')
        ->bio->toBe('Old bio')
        ->github_synced_at->toBeNull()
        ->and($sshKey->fresh())->not->toBeNull();
})->with([403, 404, 500]);

it('leaves the profile untouched when the ssh keys fail to sync', function (): void {
    $stale = User::factory()->create(['github_id' => '1', 'handle' => 'octocat']);
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);

    Http::fake([
        'api.github.com/user/583231' => Http::response(['id' => 583231, 'login' => 'octocat']),
        'api.github.com/users/octocat/keys*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh())
        ->handle->toBe('old-handle')
        ->github_synced_at->toBeNull()
        ->and($stale->refresh()->handle)->toBe('octocat');
});

it('fails when github cannot be reached', function (): void {
    $user = User::factory()->create(['handle' => 'old-handle']);

    Http::fake(fn () => Http::failedConnection());

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh()->handle)->toBe('old-handle');
});

it('frees the stale handle when syncing a renamed github account', function (string $staleUsername): void {
    $stale = User::factory()->create(['github_id' => '1', 'handle' => $staleUsername, 'bio' => 'Renamed away']);
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle', 'email' => 'private@example.com']);

    fakeGitHubAccount($user, ['login' => 'octocat']);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeTrue()
        ->and($user->refresh())
        ->handle->toBe('octocat')
        ->github_id->toBe('583231')
        ->email->toBe('private@example.com')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());

    expect($stale->refresh())
        ->handle->toBe('_renamed-1')
        ->github_id->toBe('1')
        ->bio->toBe('Renamed away')
        ->and(User::query()->count())->toBe(2);
})->with(['octocat', 'OctoCat']);
