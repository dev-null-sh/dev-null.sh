<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

beforeEach(function (): void {
    config([
        'services.github.client_id' => 'client-id',
        'services.github.client_secret' => 'client-secret',
    ]);
});

/**
 * GitHub knows the followed account by its ID, under whatever login it has now.
 *
 * @param  array<string, mixed>  $responses
 */
function fakeGitHubFollow(User $user, ?string $login = null, array $responses = []): void
{
    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $login ?? $user->username]),
        'api.github.com/user/following/*' => Http::response(status: 204),
        'api.github.com/applications/client-id/token' => Http::response(status: 204),
        ...$responses,
    ]);
}

function fakeGitHubFollowToken(User $follower, ?string $githubId = null): void
{
    Socialite::fake('github-follow', GitHubUser::fake([
        'id' => $githubId ?? $follower->github_id,
        'nickname' => $follower->username,
        'token' => 'gho_follow',
    ]));
}

function assertGitHubFollowTokenRevoked(): void
{
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://api.github.com/applications/client-id/token'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
        && $request['access_token'] === 'gho_follow');
}

it('asks github for the follow scope alone, coming back to its own callback', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($follower)->post(route('user-github-follow.create', $user));

    $response->assertRedirect()->assertSessionHas('github-follow', $user->id);

    $url = parse_url((string) $response->headers->get('Location'));
    parse_str($url['query'] ?? '', $query);

    expect($url['host'] ?? null)->toBe('github.com')
        ->and($url['path'] ?? null)->toBe('/login/oauth/authorize')
        ->and($query['scope'] ?? null)->toBe('user:follow')
        ->and($query['redirect_uri'] ?? null)->toBe(route('user-github-follow.store'));
});

it('sends inertia visits to github as a full page visit', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($follower)
        ->withHeader('X-Inertia', 'true')
        ->post(route('user-github-follow.create', $user));

    $response->assertConflict();

    expect($response->headers->get('X-Inertia-Location'))->toStartWith('https://github.com/login/oauth/authorize?');
});

it('may not follow on github', function (Closure $setup): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $user = $setup($follower, $user);

    $response = $this->actingAs($follower)->post(route('user-github-follow.create', $user));

    $response->assertForbidden()->assertSessionMissing('github-follow');
})->with([
    'themselves' => [fn (User $follower): User => $follower],
    'tom, who has no github account' => [fn (): User => User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID])],
    'across a block' => [function (User $follower, User $user): User {
        $user->blocking()->attach($follower);

        return $user;
    }],
]);

it('requires authentication to follow on github', function (): void {
    $user = User::factory()->create();

    $this->post(route('user-github-follow.create', $user))->assertRedirectToRoute('home');
    $this->get(route('user-github-follow.store'))->assertRedirectToRoute('home');
});

it('follows on github with the token of the follower, and revokes it', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    fakeGitHubFollowToken($follower);
    fakeGitHubFollow($user);

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store', ['code' => 'code', 'state' => 'state']));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertSessionMissing('github-follow')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => "You follow @{$user->username} on GitHub.",
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === "https://api.github.com/user/following/{$user->username}"
        && $request->hasHeader('Authorization', 'Bearer gho_follow'));

    assertGitHubFollowTokenRevoked();

    expect($follower->isFollowing($user))->toBeFalse();
});

it('follows the account by its id, whatever its login is now', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create(['username' => 'old-login']);

    fakeGitHubFollowToken($follower);
    fakeGitHubFollow($user, login: 'new-login');

    $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === "https://api.github.com/user/{$user->github_id}"
        && $request->hasHeader('Authorization', 'Bearer gho_follow'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/user/following/new-login');
    Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/user/following/old-login');
});

it('follows nobody from another github account, and revokes its token', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    fakeGitHubFollowToken($follower, githubId: '42');
    fakeGitHubFollow($user);

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store'));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => "GitHub is signed in to another account. Switch to @{$follower->username} on GitHub and try again.",
        ]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'user/following'));

    assertGitHubFollowTokenRevoked();
});

it('says so when github does not follow, and still revokes the token', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    fakeGitHubFollowToken($follower);
    fakeGitHubFollow($user, responses: [
        'api.github.com/user/following/*' => Http::response(status: 403),
    ]);

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store'));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => "GitHub did not follow @{$user->username}. Try again in a minute.",
        ]);

    assertGitHubFollowTokenRevoked();
});

it('says so when the account is gone from github, and still revokes the token', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    fakeGitHubFollowToken($follower);
    fakeGitHubFollow($user, responses: [
        "api.github.com/user/{$user->github_id}" => Http::response(status: 404),
    ]);

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store'));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => "@{$user->username} is no longer on GitHub.",
        ]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'user/following'));

    assertGitHubFollowTokenRevoked();
});

it('changes nothing when the follower does not allow it on github', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store', ['error' => 'access_denied']));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'GitHub was not allowed to follow. Nothing changed.',
        ]);

    Http::assertNothingSent();
});

it('changes nothing when the state does not match', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    Socialite::fake('github-follow', fn () => throw new InvalidStateException());

    $response = $this->actingAs($follower)
        ->withSession(['github-follow' => $user->id])
        ->get(route('user-github-follow.store'));

    $response->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Unable to follow on GitHub. Please try again.',
        ]);

    Http::assertNothingSent();
});

it('changes nothing without a follow to finish', function (): void {
    $follower = User::factory()->create();

    $response = $this->actingAs($follower)->get(route('user-github-follow.store', ['code' => 'code']));

    $response->assertRedirectToRoute('log.show', $follower->log);

    Http::assertNothingSent();
});
