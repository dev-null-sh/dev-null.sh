<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Services\AppSignIn;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GitHubUser;

beforeEach(function (): void {
    Queue::fake();
});

const APP_VERIFIER = 'a-verifier-only-the-app-knows-0123456789-abcdefghijklmnop';

function appChallenge(): string
{
    return AppSignIn::challengeFor(APP_VERIFIER);
}

/** The code in the URL that hands the user back to the app. */
function appCode(string $location): string
{
    expect($location)->toStartWith(AppSignIn::CALLBACK.'?code=');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return (string) $query['code'];
}

it('starts the usual sign-in for a guest', function (): void {
    $this->get(route('app-session.create', ['challenge' => appChallenge()]))
        ->assertRedirectToRoute('login');
});

it('opens the local sign-in modal on the home page locally, where signing in goes back a page', function (): void {
    app()->instance('env', 'local');

    $this->get(route('app-session.create', ['challenge' => appChallenge()]))
        ->assertRedirectToRoute('home')
        ->assertInertiaFlash('localSignIn', true);
});

it('refuses a malformed challenge', function (): void {
    $this->get(route('app-session.create', ['challenge' => 'short']))
        ->assertSessionHasErrors('challenge');
});

it('hands a user signed in to the browser back to the app at once', function (): void {
    $user = User::factory()->create();

    $location = $this->actingAs($user)
        ->get(route('app-session.create', ['challenge' => appChallenge()]))
        ->assertRedirect()
        ->headers->get('Location');

    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->get(route('app-session.store', ['code' => appCode((string) $location), 'verifier' => APP_VERIFIER]))
        ->assertRedirect(route('log.show', $user->log, absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('hands the user back to the app instead of signing in the browser after GitHub', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
    ]));

    $this->get(route('app-session.create', ['challenge' => appChallenge()]));

    $location = $this->get(route('login.store'))
        ->assertRedirect()
        ->headers->get('Location');

    $this->assertGuest();

    $user = User::query()->where('github_id', '583231')->sole();

    $this->get(route('app-session.store', ['code' => appCode((string) $location), 'verifier' => APP_VERIFIER]))
        ->assertRedirectToRoute('onboarding.show')
        ->assertInertiaFlash('signedUp', true);

    $this->assertAuthenticatedAs($user);
});

it('signs in the browser as usual once the app has been handed back', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    Socialite::fake('github', GitHubUser::fake(['id' => '583231', 'nickname' => 'octocat']));

    $this->get(route('app-session.create', ['challenge' => appChallenge()]));
    $this->get(route('login.store'));

    $this->get(route('login.store'))->assertRedirectToRoute('onboarding.show');

    $this->assertAuthenticated();
});

it('refuses a code with the wrong verifier, and spends it', function (): void {
    $user = User::factory()->create();
    $code = appCode(resolve(AppSignIn::class)->callbackUrl($user, appChallenge()));

    $this->get(route('app-session.store', ['code' => $code, 'verifier' => 'not-the-verifier']))
        ->assertRedirectToRoute('home');

    $this->get(route('app-session.store', ['code' => $code, 'verifier' => APP_VERIFIER]))
        ->assertRedirectToRoute('home');

    $this->assertGuest();
});

it('refuses a code used twice', function (): void {
    $user = User::factory()->create();
    $code = appCode(resolve(AppSignIn::class)->callbackUrl($user, appChallenge()));

    $this->get(route('app-session.store', ['code' => $code, 'verifier' => APP_VERIFIER]));
    $this->post(route('logout'));

    $this->get(route('app-session.store', ['code' => $code, 'verifier' => APP_VERIFIER]))
        ->assertRedirectToRoute('home');

    $this->assertGuest();
});
