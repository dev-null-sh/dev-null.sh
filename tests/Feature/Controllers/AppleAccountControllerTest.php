<?php

declare(strict_types=1);

use App\Jobs\RevokeAppleToken;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('unlinks sign in with apple from the web, and has apple revoke its refresh token', function (): void {
    Queue::fake();

    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234', 'apple_refresh_token' => 'the-refresh-token']);

    $response = $this->actingAs($user)
        ->fromRoute('device.index')
        ->delete(route('apple-account.destroy'));

    $response->assertRedirectToRoute('device.index')
        ->assertInertiaFlash('toast.message', 'Unlinked Sign in with Apple.');

    expect($user->refresh()->apple_id)->toBeNull();

    Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job): bool => $job->refreshToken === 'the-refresh-token');
});

it('requires sign in', function (): void {
    $this->delete(route('apple-account.destroy'))->assertRedirectToRoute('home');
});
