<?php

declare(strict_types=1);

use App\Models\User;

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

it('renders the rss feed link', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $token = $user->refresh()->feed_token;

    expect($token)->toHaveLength(48);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('feedUrl', route('following-feed.show', $token))
            ->missing('auth.user.feed_token'));
});

it('keeps the existing rss feed link', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('feedUrl', route('following-feed.show', 'secret-feed-token')));

    expect($user->refresh()->feed_token)->toBe('secret-feed-token');
});

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
