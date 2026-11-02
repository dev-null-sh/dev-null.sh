<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Enums\NotificationType;
use App\Http\Controllers\SessionController;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Router;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/**
 * The local sign-in routes are only registered in the local environment, so the routes are loaded again in it.
 */
function registerRoutes(string $env): void
{
    app()->instance('env', $env);

    Route::middleware('web')->group(base_path('routes/web.php'));

    resolve(Router::class)->getRoutes()->refreshNameLookups();
    resolve(Router::class)->getRoutes()->refreshActionLookups();
}

beforeEach(function (): void {
    session()->start();
});

it('opens the sign in modal on the page the guest was reading', function (): void {
    registerRoutes('local');

    $user = User::factory()->create();

    $this->from(route('user.show', $user))
        ->get(route('login'))
        ->assertRedirect(route('user.show', $user))
        ->assertInertiaFlash('localSignIn', true);
});

it('opens the sign in modal on the home page without a previous page', function (): void {
    registerRoutes('local');

    $this->get(route('login'))
        ->assertRedirectToRoute('home')
        ->assertInertiaFlash('localSignIn', true);
});

it('creates a user for a new handle and signs them in', function (): void {
    registerRoutes('local');

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => 'octocat'])
        ->assertRedirectToRoute('log.show', Log::Following);

    $user = User::query()->whereRelation('profile', 'handle', 'octocat')->sole();

    expect($user)
        ->github_id->toBe('local:octocat')
        ->name->toBe('octocat');

    $this->assertAuthenticatedAs($user);
});

it('signs in as the existing user of a handle', function (): void {
    registerRoutes('local');

    $user = User::factory()->create(['handle' => 'octocat', 'log' => Log::Trending]);

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => 'octocat'])
        ->assertRedirectToRoute('log.show', Log::Trending);

    $this->assertAuthenticatedAs($user);

    expect(User::query()->count())->toBe(1);
});

it('signs in as the seeded local developer', function (): void {
    registerRoutes('local');

    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => $localDeveloper->handle])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($localDeveloper);

    expect($localDeveloper->notifications()->get()->pluck('type')->all())
        ->toContain(NotificationType::Followed, NotificationType::Starred, NotificationType::Replied, NotificationType::Mentioned)
        ->and($localDeveloper->notifications()->unread()->count())->toBe(12);
});

it('seeds activity for a local developer who already signed in', function (): void {
    registerRoutes('local');

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => 'local-dev'])
        ->assertSessionHasNoErrors();

    $localDeveloper = User::query()->where('github_id', 'local:local-dev')->sole();

    $this->seed(DatabaseSeeder::class);

    $this->assertAuthenticatedAs($localDeveloper);

    expect($localDeveloper->commits()->topLevel()->count())->toBe(3)
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->notifications()->unread()->count())->toBe(12);

    $this->get(route('conversation.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('inbox.conversations', 4));
});

it('loads the signed in page in full, as signing in with GitHub does', function (): void {
    registerRoutes('local');

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => 'octocat'], ['X-Inertia' => 'true'])
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', route('log.show', Log::Following));
});

it('keeps the sign in redirect secure only behind a configured proxy', function (?string $proxies, string $remoteAddress, string $scheme): void {
    registerRoutes('local');

    $environment = Env::getRepository();
    $original = $environment->get('TRUSTED_PROXIES');
    $environment->clear('TRUSTED_PROXIES');
    $environment->set('TRUSTED_PROXIES', $proxies ?? '');

    try {
        config(['trustedproxy' => require config_path('trustedproxy.php')]);

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->post('http://preview.test/login/local', ['_token' => session()->token(), 'handle' => 'octocat'], [
                'X-Inertia' => 'true',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->assertConflict()
            ->assertHeader('X-Inertia-Location', $scheme.'://preview.test/log/following');

        $this->assertAuthenticated();
    } finally {
        $environment->clear('TRUSTED_PROXIES');

        if ($original !== null) {
            $environment->set('TRUSTED_PROXIES', $original);
        }
    }
})->with([
    'configured loopback proxy' => ['127.0.0.1,::1', '127.0.0.1', 'https'],
    'untrusted remote' => ['127.0.0.1,::1', '192.0.2.10', 'http'],
    'no configured proxies' => [null, '127.0.0.1', 'http'],
]);

it('returns to the page the guest was reading', function (): void {
    registerRoutes('local');

    $user = User::factory()->create();

    $this->get(route('user.show', $user))->assertOk();

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => 'octocat'])
        ->assertRedirect(route('user.show', $user));
});

it('refuses an invalid handle', function (string $handle): void {
    registerRoutes('local');

    $this->post(route('local-login.store'), ['_token' => session()->token(), 'handle' => $handle])
        ->assertSessionHasErrors('handle');

    $this->assertGuest();
})->with(['', '-octocat', 'octocat-', 'octo--cat', 'octo/cat', str_repeat('a', 40)]);

it('signs in with GitHub outside the local environment', function (): void {
    registerRoutes('production');

    expect(Route::has('local-login.store'))->toBeFalse()
        ->and(Route::getRoutes()->getByName('login')->getActionName())->toBe(SessionController::class.'@create');
});
