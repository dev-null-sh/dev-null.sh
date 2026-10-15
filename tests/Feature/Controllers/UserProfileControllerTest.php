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

it('renders notification settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('notification-settings.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notification-settings/edit'));
});

it('requires authentication', function (): void {
    $response = $this->get(route('user-profile.edit'));

    $response->assertRedirectToRoute('home');
});
