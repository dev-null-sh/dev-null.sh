<?php

declare(strict_types=1);

use App\Jobs\RevokeAppleToken;
use App\Models\User;
use App\Services\AppleTokens;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AppleTokenIssuer;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->issuer = AppleTokenIssuer::generate();

    fakeAppleKeys($this->issuer);
});

it('links the apple id of the token to the user', function (): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), [
        'identity_token' => $this->issuer->token(),
        'nonce' => 'raw-nonce',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.lane', $this->user->lane())
        ->assertJsonPath('apple_linked', true)
        ->assertJsonMissingPath('data.apple_id');

    expect($this->user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234');

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('apple_linked', true);
});

it('links the same apple id again, or replaces the one linked before', function (string $sub): void {
    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234'])->save();

    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), [
        'identity_token' => $this->issuer->token(['sub' => $sub]),
        'nonce' => 'raw-nonce',
    ])->assertOk()->assertJsonPath('apple_linked', true);

    expect($this->user->refresh()->apple_id)->toBe($sub);
})->with(['001234.0a1b2c3d4e5f.1234', '009999.ffffffffffff.9999']);

it('keeps a refresh token for the authorization code that comes with the token', function (): void {
    configureAppleKey();
    Http::fake([AppleTokens::TokenUrl => Http::response(['refresh_token' => 'the-refresh-token', 'id_token' => $this->issuer->token()])]);

    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), [
        'identity_token' => $this->issuer->token(),
        'nonce' => 'raw-nonce',
        'authorization_code' => 'the-code',
    ])->assertOk()
        ->assertJsonPath('apple_linked', true)
        ->assertJsonMissingPath('data.apple_refresh_token');

    expect($this->user->refresh()->apple_refresh_token)->toBe('the-refresh-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === AppleTokens::TokenUrl && $request['code'] === 'the-code');
});

it('links the apple id anyway when apple does not swap the authorization code, or no key is configured', function (bool $configured): void {
    if ($configured) {
        configureAppleKey();
    }

    Http::fake([AppleTokens::TokenUrl => Http::response(['error' => 'invalid_grant'], 400)]);

    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), [
        'identity_token' => $this->issuer->token(),
        'nonce' => 'raw-nonce',
        'authorization_code' => 'the-code',
    ])->assertOk()->assertJsonPath('apple_linked', true);

    expect($this->user->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234')
        ->and($this->user->apple_refresh_token)->toBeNull();
})->with(['apple turns the code down' => true, 'no key' => false]);

it('refuses a token apple did not make for the app and the nonce', function (Closure $data): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), $data($this->issuer))
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Sign in with Apple did not work. Try again.', 'code' => 'invalid_apple_token']);

    expect($this->user->refresh()->apple_id)->toBeNull();
})->with([
    'another nonce' => fn (AppleTokenIssuer $issuer): array => ['identity_token' => $issuer->token(), 'nonce' => 'other-nonce'],
    'another audience' => fn (AppleTokenIssuer $issuer): array => ['identity_token' => $issuer->token(['aud' => 'com.example.other']), 'nonce' => 'raw-nonce'],
    'another issuer' => fn (AppleTokenIssuer $issuer): array => ['identity_token' => $issuer->token(['iss' => 'https://evil.example']), 'nonce' => 'raw-nonce'],
    'expired' => fn (AppleTokenIssuer $issuer): array => ['identity_token' => $issuer->token(['exp' => now()->subMinute()->getTimestamp()]), 'nonce' => 'raw-nonce'],
    'an unknown key' => fn (): array => ['identity_token' => AppleTokenIssuer::generate('unknown-key')->token(), 'nonce' => 'raw-nonce'],
    'a bad signature' => fn (AppleTokenIssuer $issuer): array => ['identity_token' => AppleTokenIssuer::generate($issuer->kid)->token(), 'nonce' => 'raw-nonce'],
    'not a token' => fn (): array => ['identity_token' => 'not-a-token', 'nonce' => 'raw-nonce'],
]);

it('refuses an apple id linked to someone else', function (): void {
    $other = User::factory()->create(['apple_id' => '001234.0a1b2c3d4e5f.1234']);

    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), [
        'identity_token' => $this->issuer->token(),
        'nonce' => 'raw-nonce',
    ])->assertConflict()
        ->assertExactJson(['message' => 'This Apple ID is already linked to another /dev/null account.', 'code' => 'apple_linked_elsewhere']);

    expect($this->user->refresh()->apple_id)->toBeNull()
        ->and($other->refresh()->apple_id)->toBe('001234.0a1b2c3d4e5f.1234');
});

it('validates the token, the nonce and the authorization code', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no token' => [['nonce' => 'raw-nonce'], 'identity_token'],
    'no nonce' => [['identity_token' => 'token'], 'nonce'],
    'a code that is not a string' => [['identity_token' => 'token', 'nonce' => 'raw-nonce', 'authorization_code' => ['the-code']], 'authorization_code'],
    'a long code' => [['identity_token' => 'token', 'nonce' => 'raw-nonce', 'authorization_code' => str_repeat('a', 1025)], 'authorization_code'],
]);

it('unlinks the apple id of the user, and has apple revoke its refresh token', function (): void {
    Queue::fake();

    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234', 'apple_refresh_token' => 'the-refresh-token'])->save();

    signedApiRequest($this->keyPair, 'DELETE', route('api-apple-account.destroy'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('apple_linked', false);

    expect($this->user->refresh()->apple_id)->toBeNull()
        ->and($this->user->apple_refresh_token)->toBeNull();

    Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job): bool => $job->refreshToken === 'the-refresh-token');

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('apple_linked', false);
});

it('limits linking and unlinking to 10 a minute, with a code', function (): void {
    foreach (range(1, 10) as $attempt) {
        signedApiRequest($this->keyPair, 'DELETE', route('api-apple-account.destroy'))->assertOk();
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-apple-account.update'), ['identity_token' => $this->issuer->token(), 'nonce' => 'raw-nonce'])
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);
});

it('rejects requests that are not signed', function (string $method): void {
    $this->json($method, route('api-apple-account.update'), ['identity_token' => $this->issuer->token(), 'nonce' => 'raw-nonce'])
        ->assertUnauthorized();

    expect($this->user->refresh()->apple_id)->toBeNull();
})->with(['PUT', 'DELETE']);
