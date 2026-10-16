<?php

declare(strict_types=1);

use App\Models\User;

it('unlinks sign in with apple from the web', function (): void {
    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234']);

    $response = $this->actingAs($user)
        ->fromRoute('device.index')
        ->delete(route('apple-account.destroy'));

    $response->assertRedirectToRoute('device.index')
        ->assertInertiaFlash('toast.message', 'Unlinked Sign in with Apple.');

    expect($user->refresh()->apple_id)->toBeNull();
});

it('requires sign in', function (): void {
    $this->delete(route('apple-account.destroy'))->assertRedirectToRoute('home');
});
