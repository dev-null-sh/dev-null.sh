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

it('requires authentication', function (): void {
    $response = $this->get(route('user-profile.edit'));

    $response->assertRedirectToRoute('home');
});
