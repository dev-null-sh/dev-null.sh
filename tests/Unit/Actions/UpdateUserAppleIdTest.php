<?php

declare(strict_types=1);

use App\Actions\UpdateUserAppleId;
use App\Jobs\RevokeAppleToken;
use App\Models\User;
use App\Services\AppleTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AppleTokenIssuer;

beforeEach(function (): void {
    Queue::fake();
});

it('links an apple id to the user', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234');

    expect($user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234')
        ->and($user->apple_refresh_token)->toBeNull()
        ->and($user->toArray())->not->toHaveKeys(['apple_id', 'apple_refresh_token']);
});

it('keeps the refresh token apple swaps the authorization code for, encrypted', function (): void {
    configureAppleKey();
    Http::fake([AppleTokens::TokenUrl => Http::response(['refresh_token' => 'the-refresh-token', 'id_token' => AppleTokenIssuer::generate()->token()])]);

    $user = User::factory()->create();

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234', 'the-code');

    expect($user->refresh()->apple_refresh_token)->toBe('the-refresh-token')
        ->and(DB::table('users')->where('id', $user->id)->value('apple_refresh_token'))->not->toContain('the-refresh-token');
});

it('links the apple id without a refresh token when apple does not swap the code', function (): void {
    configureAppleKey();
    Http::fake([AppleTokens::TokenUrl => Http::response(['error' => 'invalid_grant'], 400)]);

    $user = User::factory()->create();

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234', 'the-code');

    expect($user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234')
        ->and($user->apple_refresh_token)->toBeNull();
});

it('keeps the refresh token it has when the same apple id is linked again', function (?string $authorizationCode): void {
    configureAppleKey();
    Http::fake([AppleTokens::TokenUrl => Http::response(['error' => 'invalid_grant'], 400)]);

    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234', 'apple_refresh_token' => 'the-refresh-token']);

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234', $authorizationCode);

    expect($user->refresh()->apple_refresh_token)->toBe('the-refresh-token');

    Queue::assertNothingPushed();
})->with(['without a code' => null, 'with a code apple turns down' => 'the-code']);

it('replaces the refresh token when apple swaps a new code for the same apple id, without revoking the old one', function (): void {
    configureAppleKey();
    Http::fake([AppleTokens::TokenUrl => Http::response(['refresh_token' => 'the-new-refresh-token', 'id_token' => AppleTokenIssuer::generate()->token()])]);

    $user = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234', 'apple_refresh_token' => 'the-refresh-token']);

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234', 'the-code');

    expect($user->refresh()->apple_refresh_token)->toBe('the-new-refresh-token');

    Queue::assertNothingPushed();
});

it('revokes the refresh token of the apple id it replaces', function (): void {
    $user = User::factory()->create(['apple_id' => '009999.ffffffffffff.9999', 'apple_refresh_token' => 'the-old-refresh-token']);

    resolve(UpdateUserAppleId::class)->handle($user, '001234.0a1b2c3d4e5f.1234');

    expect($user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234')
        ->and($user->apple_refresh_token)->toBeNull();

    Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job): bool => $job->refreshToken === 'the-old-refresh-token');
});
