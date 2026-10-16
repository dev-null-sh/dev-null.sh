<?php

declare(strict_types=1);

use App\Actions\UpdateUserProfileFromGitHub;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @param  array<string, mixed>  $account
 */
function fakeGitHubAccount(User $user, array $account = []): void
{
    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response([
            'id' => (int) $user->github_id,
            'login' => $user->username,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'location' => $user->location,
            'blog' => $user->website,
            'company' => $user->company,
            ...$account,
        ]),
    ]);
}

it('refreshes the profile from github', function (): void {
    $user = User::factory()->create([
        'github_id' => '583231',
        'username' => 'octocat',
        'bio' => 'Old bio',
        'github_synced_at' => now()->subDays(2),
    ]);

    fakeGitHubAccount($user, [
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=5',
        'bio' => ' New bio ',
        'location' => 'San Francisco',
        'blog' => 'github.blog',
        'company' => '',
    ]);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    expect($user->refresh())
        ->username->toBe('octocat')
        ->name->toBe('The Octocat')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231?v=5')
        ->bio->toBe('New bio')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->company->toBeNull()
        ->and($user->github_synced_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('falls back to the username when the name is blank', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    fakeGitHubAccount($user, ['name' => null]);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    expect($user->refresh()->name)->toBe('octocat');
});

it('follows a username change on github', function (): void {
    $stale = User::factory()->create(['github_id' => '1', 'username' => 'octocat']);
    $user = User::factory()->create(['github_id' => '583231', 'username' => 'old-octocat']);

    fakeGitHubAccount($user, ['login' => 'Octocat']);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    expect($user->refresh()->username)->toBe('Octocat')
        ->and($stale->refresh()->username)->toBe('_renamed-1');
});

it('authenticates with the server token', function (): void {
    config(['services.github.token' => 'ghp_server', 'services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);

    $user = User::factory()->create();

    fakeGitHubAccount($user);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    Http::assertSent(fn (Request $request): bool => $request->url() === "https://api.github.com/user/{$user->github_id}"
        && $request->hasHeader('Authorization', 'Bearer ghp_server'));
});

it('authenticates as the oauth app without a server token', function (?string $token): void {
    config(['services.github.token' => $token, 'services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);

    $user = User::factory()->create();

    fakeGitHubAccount($user);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));
})->with([
    'missing' => null,
    'blank' => '',
]);

it('keeps the profile when github cannot say', function (Closure $response, string $reason): void {
    Log::spy();

    $user = User::factory()->create([
        'username' => 'octocat',
        'bio' => 'Old bio',
        'github_synced_at' => now()->subDays(2),
    ]);

    Http::fake([
        "api.github.com/user/{$user->github_id}" => $response,
    ]);

    resolve(UpdateUserProfileFromGitHub::class)->handle($user);

    expect($user->refresh())
        ->username->toBe('octocat')
        ->bio->toBe('Old bio')
        ->and($user->github_synced_at?->toDateTimeString())->toBe(now()->toDateTimeString());

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Could not refresh the GitHub profile.', Mockery::on(fn (array $context): bool => $context['username'] === 'octocat'
            && str_contains((string) $context['reason'], $reason)));
})->with([
    'deleted account' => [fn () => Http::response(['message' => 'Not Found'], 404), 'no longer exists'],
    'rate limited' => [fn () => Http::response(['message' => 'API rate limit exceeded'], 403), '403'],
    'too many requests' => [fn () => Http::response(status: 429), '429'],
    'server error' => [fn () => Http::response(status: 502), '502'],
    'connection failure' => [fn () => Http::failedConnection(), ''],
]);
