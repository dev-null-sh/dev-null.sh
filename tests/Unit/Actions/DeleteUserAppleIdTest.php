<?php

declare(strict_types=1);

use App\Actions\DeleteUserAppleId;
use App\Jobs\RevokeAppleToken;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('unlinks the apple id of the user, and has apple revoke its refresh token', function (): void {
    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234', 'apple_refresh_token' => 'the-refresh-token']);

    resolve(DeleteUserAppleId::class)->handle($user);

    expect($user->refresh()->apple_id)->toBeNull()
        ->and($user->apple_refresh_token)->toBeNull();

    Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job): bool => $job->refreshToken === 'the-refresh-token');
});

it('revokes nothing when it kept no refresh token', function (): void {
    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234']);

    resolve(DeleteUserAppleId::class)->handle($user);

    expect($user->refresh()->apple_id)->toBeNull();

    Queue::assertNothingPushed();
});
