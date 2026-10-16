<?php

declare(strict_types=1);

use App\Services\AppleTokens;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use phpseclib4\Crypt\EC;
use Tests\Support\AppleTokenIssuer;

beforeEach(function (): void {
    $this->key = configureAppleKey();
    $this->issuer = AppleTokenIssuer::generate();
});

it('swaps the authorization code for a refresh token of the apple id', function (): void {
    Http::fake([AppleTokens::TokenUrl => Http::response([
        'access_token' => 'the-access-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refresh_token' => 'the-refresh-token',
        'id_token' => $this->issuer->token(),
    ])]);

    expect(resolve(AppleTokens::class)->refreshToken('the-code', '001234.0a1b2c3d4e5f.1234'))->toBe('the-refresh-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === AppleTokens::TokenUrl
        && $request->isForm()
        && $request['client_id'] === 'com.simonhamp.devnull'
        && is_string($request['client_secret'])
        && $request['code'] === 'the-code'
        && $request['grant_type'] === 'authorization_code');
});

it('revokes a refresh token', function (): void {
    Http::fake([AppleTokens::RevokeUrl => Http::response()]);

    resolve(AppleTokens::class)->revoke('the-refresh-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === AppleTokens::RevokeUrl
        && $request->isForm()
        && $request['client_id'] === 'com.simonhamp.devnull'
        && is_string($request['client_secret'])
        && $request['token'] === 'the-refresh-token'
        && $request['token_type_hint'] === 'refresh_token');
});

it('signs a client secret for the team and the app that lasts five minutes', function (): void {
    $this->freezeTime();

    Http::fake([AppleTokens::RevokeUrl => Http::response()]);

    resolve(AppleTokens::class)->revoke('the-refresh-token');

    $request = Http::recorded()->sole()[0];
    [$header, $claims, $signature] = explode('.', $request['client_secret']);
    $decode = fn (string $part): string => (string) base64_decode(strtr($part, '-_', '+/'), true);

    expect(json_decode($decode($header), true))->toBe(['alg' => 'ES256', 'kid' => 'ABC123DEFG'])
        ->and(json_decode($decode($claims), true))->toBe([
            'iss' => 'J68WFCX458',
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'aud' => 'https://appleid.apple.com',
            'sub' => 'com.simonhamp.devnull',
        ])
        ->and(mb_strlen($decode($signature), '8bit'))->toBe(64)
        ->and($this->key->getPublicKey()->withSignatureFormat('IEEE')->withHash('sha256')->verify("{$header}.{$claims}", $decode($signature)))->toBeTrue();
});

it('gives up on the refresh token, and logs why, when apple does not hand one over for the apple id', function (Closure $response): void {
    Log::spy();
    Http::fake([AppleTokens::TokenUrl => $response($this->issuer)]);

    expect(resolve(AppleTokens::class)->refreshToken('the-code', '001234.0a1b2c3d4e5f.1234'))->toBeNull();

    Log::shouldHaveReceived('warning')->once();
})->with([
    'a code apple turns down' => fn (): PromiseInterface => Http::response(['error' => 'invalid_grant'], 400),
    'apple down' => fn (): PromiseInterface => Http::response(status: 503),
    'apple unreachable' => fn (): Closure => Http::failedConnection(),
    'no refresh token' => fn (AppleTokenIssuer $issuer): PromiseInterface => Http::response(['id_token' => $issuer->token()]),
    'no id token' => fn (): PromiseInterface => Http::response(['refresh_token' => 'the-refresh-token']),
    'an id token that is not one' => fn (): PromiseInterface => Http::response(['refresh_token' => 'the-refresh-token', 'id_token' => 'not-a-token']),
    'a token for another apple id' => fn (AppleTokenIssuer $issuer): PromiseInterface => Http::response(['refresh_token' => 'the-refresh-token', 'id_token' => $issuer->token(['sub' => '009999.ffffffffffff.9999'])]),
]);

it('gives up on the refresh token, and logs why, when the key is not one it can sign with', function (Closure $key): void {
    Log::spy();
    Http::fake();

    config(['services.apple.private_key' => $key()]);

    expect(resolve(AppleTokens::class)->refreshToken('the-code', '001234.0a1b2c3d4e5f.1234'))->toBeNull();

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
})->with([
    'not a key' => fn (): string => 'not a key',
    'a public key' => fn (): string => EC::createKey('nistp256')->getPublicKey()->toString('PKCS8'),
]);

it('throws when apple does not revoke the token, so it can be tried again', function (): void {
    Http::fake([AppleTokens::RevokeUrl => Http::response(['error' => 'invalid_client'], 400)]);

    expect(fn () => resolve(AppleTokens::class)->revoke('the-refresh-token'))->toThrow(RequestException::class);
});

it('sends nothing to apple until the key is configured, and logs that once', function (string $setting, ?string $value): void {
    Log::spy();
    Http::fake();

    config(["services.apple.{$setting}" => $value]);

    expect(resolve(AppleTokens::class)->refreshToken('the-code', '001234.0a1b2c3d4e5f.1234'))->toBeNull();

    resolve(AppleTokens::class)->revoke('the-refresh-token');
    resolve(AppleTokens::class)->revoke('the-refresh-token');

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')
        ->with('Sign in with Apple tokens are not swapped or revoked until APPLE_CLIENT_ID, APPLE_TEAM_ID, APPLE_KEY_ID and APPLE_PRIVATE_KEY are set.')
        ->once();
})->with([
    'no client id' => ['client_id', null],
    'no team' => ['team_id', null],
    'no key id' => ['key_id', null],
    'no key' => ['private_key', null],
    'an empty key' => ['private_key', ''],
]);
