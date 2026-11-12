<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

it('renders profile settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/edit'));
});

it('renders the chosen and automatic lanes', function (?int $preferredLane): void {
    $user = User::factory()->create(['id' => '3a788e93-4cf7-4819-9377-94be2f329563', 'preferred_lane' => $preferredLane]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preferredLane', $preferredLane)
            ->where('automaticLane', 4)
            ->where('auth.user.lane', $preferredLane ?? 4));
})->with([null, 1]);

it('renders the custom color as the chosen lane', function (): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preferredLane', '#1a2b3c')
            ->where('auth.user.lane', '#1a2b3c'));
});

it('renders the activity graph links with the timezone preference', function (?string $timezone): void {
    $user = User::factory()->create(['handle' => 'octocat', 'timezone' => $timezone]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activityGraphUrl', url('/@octocat/graph.svg'))
            ->where('profileUrl', url('/@octocat'))
            ->where('auth.user.timezone', $timezone));
})->with([null, 'Europe/Budapest']);

it('redirects settings to profile settings', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/settings');

    $response->assertRedirectToRoute('user-profile.edit');
});

it('renders appearance settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('appearance.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('appearance/update'));
});

it('renders terminal settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('terminal.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('terminal/show')
            ->where('url', url('/')));
});

it('requires authentication', function (string $route): void {
    $response = $this->get(route($route));

    $response->assertRedirectToRoute('home');
})->with(['user-profile.edit', 'appearance.edit', 'terminal.show']);

it('renders when the profile was last synced from github', function (?string $syncedAt): void {
    $user = User::factory()->create(['github_synced_at' => $syncedAt]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('githubSyncedAt', $syncedAt === null ? null : now()->parse($syncedAt)->toIso8601String()));
})->with([null, '2026-10-01 09:30:00']);

it('may sync the profile from github', function (): void {
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle', 'bio' => 'Old bio']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat', 'bio' => 'New bio']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Synced from GitHub.',
        ]);

    expect($user->refresh())
        ->handle->toBe('octocat')
        ->bio->toBe('New bio')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

it('tells the user when github could not be synced', function (): void {
    $user = User::factory()->create(['handle' => 'old-handle']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andThrow(new RequestException(new Response(new Psr7Response(500))));

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Could not sync from GitHub. Try again in a minute.',
        ]);

    expect($user->refresh()->handle)->toBe('old-handle');
});

it('allows one sync a minute', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'You just synced. Try again in a minute.',
        ]);

    $github->shouldHaveReceived('account')->once();

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'))
        ->assertInertiaFlash('toast.type', 'success');

    $github->shouldReceive('account')->with($other->github_id)->andReturn(['login' => 'hubot']);
    $github->shouldReceive('login')->with($other->github_id)->andReturn('hubot');
    $github->shouldReceive('sshKeys')->with('hubot')->andReturn([]);

    $this->actingAs($other)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'))
        ->assertInertiaFlash('toast.type', 'success');
});

it('requires authentication to sync', function (): void {
    $github = $this->mock(GitHubProvider::class);

    $response = $this->put(route('user-profile.update'));

    $response->assertRedirectToRoute('home');

    $github->shouldNotHaveReceived('account');
});

it('shows the private translation preference and supported choices only in own settings', function (): void {
    $user = User::factory()->create(['translation_language' => 'ja']);
    $other = User::factory()->create(['translation_language' => 'de']);

    $this->actingAs($user)->get(route('user-profile.edit', ['user_id' => $other->id]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('translationLanguage', 'ja')
        ->where('translationLanguages', fn ($languages): bool => collect($languages)->contains(fn ($language): bool => $language['code'] === 'hu' && $language['name'] === 'Hungarian' && $language['nativeName'] === 'magyar'))
        ->missing('auth.user.translation_language'));
});
