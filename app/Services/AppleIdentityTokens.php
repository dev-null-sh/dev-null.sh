<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Crypt\RSA;
use phpseclib4\Crypt\RSA\PublicKey;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Verifies the identity token Sign in with Apple hands the mobile app: a JWT signed (RS256) with one of the keys Apple
 * publishes, issued by Apple for exactly one of the app's bundle IDs, still valid, and made for the nonce the app sends
 * along. The app gives Apple the SHA-256 of a raw nonce, in lowercase hex, and keeps the raw one, so a token is only
 * good for the request it was made for. Tokens are never stored.
 */
final readonly class AppleIdentityTokens
{
    public const string Issuer = 'https://appleid.apple.com';

    public const string KeysUrl = 'https://appleid.apple.com/auth/keys';

    private const string KeysCacheKey = 'apple-identity-keys';

    private const string KeysFetchedCacheKey = 'apple-identity-keys-fetched';

    private const int ClockSkewSeconds = 60;

    /**
     * Returns the token's `sub`, which stays the same for one Apple ID and one app team.
     *
     * @throws InvalidArgumentException when the token isn't one Apple made for this app and nonce
     */
    public function verify(string $token, string $nonce): string
    {
        $parts = explode('.', $token);

        throw_unless(count($parts) === 3, InvalidArgumentException::class, 'Malformed token.');

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;

        $header = $this->decode($encodedHeader);

        throw_unless(($header['alg'] ?? null) === 'RS256' && is_string($header['kid'] ?? null), InvalidArgumentException::class, 'Unsupported token.');

        throw_unless(
            $this->key($header['kid'])->verify("{$encodedHeader}.{$encodedClaims}", $this->base64UrlDecode($encodedSignature)),
            InvalidArgumentException::class,
            'Invalid signature.',
        );

        $claims = $this->decode($encodedClaims);
        $now = now()->getTimestamp();

        /** @var list<string> $clientIds */
        $clientIds = config('services.apple.client_ids');

        throw_unless(($claims['iss'] ?? null) === self::Issuer, InvalidArgumentException::class, 'Wrong issuer.');
        throw_unless(in_array($claims['aud'] ?? null, $clientIds, true), InvalidArgumentException::class, 'Wrong audience.');
        throw_unless(is_int($claims['exp'] ?? null) && $claims['exp'] > $now, InvalidArgumentException::class, 'Expired.');
        throw_unless(is_int($claims['iat'] ?? null) && $claims['iat'] <= $now + self::ClockSkewSeconds, InvalidArgumentException::class, 'Issued in the future.');
        throw_unless(is_string($claims['nonce'] ?? null) && hash_equals($claims['nonce'], hash('sha256', $nonce)), InvalidArgumentException::class, 'Wrong nonce.');
        throw_unless(is_string($claims['sub'] ?? null) && $claims['sub'] !== '', InvalidArgumentException::class, 'No subject.');

        return $claims['sub'];
    }

    /**
     * Apple's key with the given ID. Apple rotates its keys now and then, so a key ID it hasn't seen fetches them
     * again, at most once a minute, so made-up key IDs can't make it ask Apple on every request.
     */
    private function key(string $kid): PublicKey
    {
        /** @var array<string, array<string, mixed>>|null $keys */
        $keys = Cache::get(self::KeysCacheKey);

        if (! is_array($keys) || (! isset($keys[$kid]) && Cache::add(self::KeysFetchedCacheKey, true, 60))) {
            $keys = $this->fetchKeys();
        }

        throw_unless(isset($keys[$kid]), InvalidArgumentException::class, 'Unknown key.');

        $key = PublicKeyLoader::load((string) json_encode(['keys' => [$keys[$kid]]]));

        throw_unless($key instanceof PublicKey, InvalidArgumentException::class, 'Unsupported key.');

        return $key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchKeys(): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(5)->get(self::KeysUrl)->throw();
        } catch (ConnectionException|RequestException) {
            throw new ServiceUnavailableHttpException(message: __('Could not reach Apple to check the sign-in. Try again in a moment.'));
        }

        $published = $response->json('keys');

        /** @var array<string, array<string, mixed>> $keys */
        $keys = collect(is_array($published) ? $published : [])
            ->filter(fn (mixed $key): bool => is_array($key) && is_string($key['kid'] ?? null))
            ->keyBy('kid')
            ->all();

        Cache::put(self::KeysCacheKey, $keys, now()->addDay());

        return $keys;
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $encoded): array
    {
        try {
            $decoded = json_decode($this->base64UrlDecode($encoded), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Malformed token.');
        }

        throw_unless(is_array($decoded), InvalidArgumentException::class, 'Malformed token.');

        return $decoded;
    }

    private function base64UrlDecode(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        throw_unless(is_string($decoded), InvalidArgumentException::class, 'Malformed token.');

        return $decoded;
    }
}
