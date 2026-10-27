<?php

declare(strict_types=1);

use App\Models\User;

it('may mute a user', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create(['handle' => 'octocat']);

    $response = $this->actingAs($user)
        ->fromRoute('user.show', $muted)
        ->post(route('user-mute.store', $muted));

    $response->assertRedirectToRoute('user.show', $muted)
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => "Muted @octocat. You won't see their commits or hear from them, and they won't know.",
        ]);

    expect($user->isMuting($muted))->toBeTrue();
});

it('may not mute themselves', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('user-mute.store', $user));

    $response->assertForbidden();

    expect($user->muting()->count())->toBe(0);
});

it('may unmute a user', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create(['handle' => 'octocat']);

    $user->muting()->attach($muted);

    $response = $this->actingAs($user)
        ->fromRoute('safety.edit')
        ->delete(route('user-mute.destroy', $muted));

    $response->assertRedirectToRoute('safety.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unmuted @octocat.']);

    expect($user->isMuting($muted))->toBeFalse();
});

it('requires authentication to mute', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('user-mute.store', $user));

    $response->assertRedirectToRoute('home');

    expect($user->mutedBy()->count())->toBe(0);
});
