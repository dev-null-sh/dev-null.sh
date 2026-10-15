<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['app.debug' => false]);
});

it('renders the not found page for guests', function (): void {
    $response = $this->get('/does-not-exist');

    $response->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('auth.user', null));
});

it('renders the not found page for signed in users', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/does-not-exist');

    $response->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('auth.user.id', $user->id));
});

it('renders the not found page for a missing profile', function (): void {
    $response = $this->actingAs(User::factory()->create())->get('/@nobody');

    $response->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 404));
});

it('keeps json responses for requests that expect json', function (): void {
    $response = $this->actingAs(User::factory()->create())->getJson('/@nobody/preview');

    $response->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('renders the forbidden page for non moderators', function (): void {
    config(['app.admins' => ['admin']]);

    $response = $this->actingAs(User::factory()->create())->get(route('moderation.show'));

    $response->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 403));
});

it('renders the too many requests page for inertia requests', function (): void {
    Route::middleware(['web', 'throttle:1,1'])->get('_test/throttled', fn (): string => 'ok');

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
    ];

    $this->withHeaders($headers)->get('_test/throttled')->assertOk();

    $response = $this->withHeaders($headers)->get('_test/throttled');

    $response->assertTooManyRequests()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'error')
        ->assertJsonPath('props.status', 429);
});

it('renders the server error page without leaking the exception', function (): void {
    Route::middleware('web')->get('_test/broken', fn () => throw new RuntimeException('Kaboom'));

    $response = $this->get('_test/broken');

    $response->assertInternalServerError()
        ->assertDontSee('Kaboom')
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 500));
});

it('keeps the debug page for server errors in debug mode', function (): void {
    config(['app.debug' => true]);

    Route::middleware('web')->get('_test/broken', fn () => throw new RuntimeException('Kaboom'));

    $response = $this->get('_test/broken');

    $response->assertInternalServerError()
        ->assertSee('Kaboom');
});

it('renders the maintenance page', function (): void {
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);

    app()->maintenanceMode()->activate([]);

    $response = $this->get(route('home'));

    $response->assertServiceUnavailable()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 503)
            ->where('auth.user', null));
});

it('redirects back with a toast when the page expired', function (): void {
    Route::middleware('web')->post('_test/expired', fn () => throw new TokenMismatchException());

    $response = $this->actingAs(User::factory()->create())
        ->from('/log/following')
        ->post('_test/expired');

    $response->assertRedirect('/log/following')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Page expired: your session timed out before that went through. Try again.',
        ]);
});
