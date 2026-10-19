<?php

declare(strict_types=1);

use App\Services\AppleIdentityTokens;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Support\AppleTokenIssuer;

beforeEach(function (): void {
    $this->issuer = AppleTokenIssuer::generate();
});

it('returns the subject of a token apple signed for the app and the nonce', function (): void {
    fakeAppleKeys($this->issuer);

    expect(resolve(AppleIdentityTokens::class)->verify($this->issuer->token(), 'raw-nonce'))->toBe('001234.0a1b2c3d4e5f.1234');
});

it('accepts a token for any of the configured bundle ids', function (string $audience): void {
    fakeAppleKeys($this->issuer);

    config(['services.apple.client_ids' => ['com.simonhamp.devnull', 'com.simonhamp.devnull.beta']]);

    expect(resolve(AppleIdentityTokens::class)->verify($this->issuer->token(['aud' => $audience]), 'raw-nonce'))->toBe('001234.0a1b2c3d4e5f.1234');
})->with(['com.simonhamp.devnull', 'com.simonhamp.devnull.beta']);

it('takes a nonce claim that is the sha-256 of the raw nonce in lowercase hex', function (): void {
    fakeAppleKeys($this->issuer);

    $raw = 'Zq8p3N_xV2-rT9kLmW4yHc7bDfJ6sQaE';
    $token = $this->issuer->token(['nonce' => bin2hex(hash('sha256', $raw, true))]);

    expect(resolve(AppleIdentityTokens::class)->verify($token, $raw))->toBe('001234.0a1b2c3d4e5f.1234');
});

it('allows a little clock skew in when the token was issued', function (): void {
    fakeAppleKeys($this->issuer);

    expect(resolve(AppleIdentityTokens::class)->verify($this->issuer->token(['iat' => now()->addSeconds(60)->getTimestamp()]), 'raw-nonce'))->toBeString();
});

it('rejects a token apple did not make for the app and the nonce', function (Closure $token, string $message): void {
    fakeAppleKeys($this->issuer);

    expect(fn (): string => resolve(AppleIdentityTokens::class)->verify($token($this->issuer), 'raw-nonce'))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'another issuer' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['iss' => 'https://evil.example']), 'Wrong issuer.'],
    'another audience' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['aud' => 'com.example.other']), 'Wrong audience.'],
    'no audience' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['aud' => null]), 'Wrong audience.'],
    'a list of audiences with the app in it' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['aud' => ['com.simonhamp.devnull', 'com.example.other']]), 'Wrong audience.'],
    'expired' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['exp' => now()->getTimestamp()]), 'Expired.'],
    'no expiry' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['exp' => null]), 'Expired.'],
    'issued in the future' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['iat' => now()->addSeconds(61)->getTimestamp()]), 'Issued in the future.'],
    'no issue time' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['iat' => null]), 'Issued in the future.'],
    'another nonce' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['nonce' => hash('sha256', 'other-nonce')]), 'Wrong nonce.'],
    'the raw nonce' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['nonce' => 'raw-nonce']), 'Wrong nonce.'],
    'the nonce hashed in uppercase hex' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['nonce' => mb_strtoupper(hash('sha256', 'raw-nonce'))]), 'Wrong nonce.'],
    'the nonce hashed in base64' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['nonce' => base64_encode(hash('sha256', 'raw-nonce', true))]), 'Wrong nonce.'],
    'no nonce' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['nonce' => null]), 'Wrong nonce.'],
    'no subject' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(['sub' => '']), 'No subject.'],
    'another algorithm' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(header: ['alg' => 'HS256']), 'Unsupported token.'],
    'no key id' => [fn (AppleTokenIssuer $issuer): string => $issuer->token(header: ['kid' => null]), 'Unsupported token.'],
    'two parts' => [fn (AppleTokenIssuer $issuer): string => implode('.', array_slice(explode('.', $issuer->token()), 0, 2)), 'Malformed token.'],
    'a header that is not base64' => [fn (AppleTokenIssuer $issuer): string => '!!!.'.explode('.', $issuer->token(), 2)[1], 'Malformed token.'],
    'a header that is not json' => [fn (AppleTokenIssuer $issuer): string => 'bm90IGpzb24.'.explode('.', $issuer->token(), 2)[1], 'Malformed token.'],
    'a header that is not an object' => [fn (AppleTokenIssuer $issuer): string => 'MQ.'.explode('.', $issuer->token(), 2)[1], 'Malformed token.'],
]);

it('rejects a token whose signature does not check out', function (Closure $token): void {
    fakeAppleKeys($this->issuer);

    expect(fn (): string => resolve(AppleIdentityTokens::class)->verify($token($this->issuer), 'raw-nonce'))
        ->toThrow(InvalidArgumentException::class, 'Invalid signature.');
})->with([
    'claims changed after signing' => fn (AppleTokenIssuer $issuer): string => (function () use ($issuer): string {
        [$header, , $signature] = explode('.', $issuer->token());
        $claims = explode('.', $issuer->token(['sub' => 'someone-else']))[1];

        return "{$header}.{$claims}.{$signature}";
    })(),
    'signed by another key with the same key id' => fn (AppleTokenIssuer $issuer): string => AppleTokenIssuer::generate($issuer->kid)->token(),
    'a signature of the wrong length' => fn (AppleTokenIssuer $issuer): string => implode('.', [...array_slice(explode('.', $issuer->token()), 0, 2), 'c2lnbmF0dXJl']),
]);

it('rejects a token signed with a key apple does not publish', function (): void {
    fakeAppleKeys($this->issuer);

    expect(fn (): string => resolve(AppleIdentityTokens::class)->verify(AppleTokenIssuer::generate('unknown-key')->token(), 'raw-nonce'))
        ->toThrow(InvalidArgumentException::class, 'Unknown key.');
});

it("keeps apple's keys, so it asks apple once for any number of tokens", function (): void {
    fakeAppleKeys($this->issuer);

    resolve(AppleIdentityTokens::class)->verify($this->issuer->token(), 'raw-nonce');
    resolve(AppleIdentityTokens::class)->verify($this->issuer->token(), 'raw-nonce');

    Http::assertSentCount(1);
});

it("fetches apple's keys again for a key id it has not seen, at most once a minute", function (): void {
    $rotated = AppleTokenIssuer::generate('rotated-key');
    $keys = [$this->issuer->jwk()];

    Http::fake([AppleIdentityTokens::KeysUrl => function () use (&$keys) {
        return Http::response(['keys' => $keys]);
    }]);

    resolve(AppleIdentityTokens::class)->verify($this->issuer->token(), 'raw-nonce');

    $keys = [$this->issuer->jwk(), $rotated->jwk()];

    expect(resolve(AppleIdentityTokens::class)->verify($rotated->token(), 'raw-nonce'))->toBe('001234.0a1b2c3d4e5f.1234')
        ->and(fn (): string => resolve(AppleIdentityTokens::class)->verify(AppleTokenIssuer::generate('made-up-key')->token(), 'raw-nonce'))
        ->toThrow(InvalidArgumentException::class, 'Unknown key.');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === AppleIdentityTokens::KeysUrl);
});

it('answers 503 when apple cannot be reached to check a token', function (int $status): void {
    Http::fake([AppleIdentityTokens::KeysUrl => Http::response('', $status)]);

    expect(fn (): string => resolve(AppleIdentityTokens::class)->verify($this->issuer->token(), 'raw-nonce'))
        ->toThrow(ServiceUnavailableHttpException::class, 'Could not reach Apple to check the sign-in. Try again in a moment.');
})->with([500, 503]);
