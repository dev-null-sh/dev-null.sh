<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use phpseclib4\Crypt\EC\PrivateKey;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Exception\NoKeyLoadedException;

/**
 * Apple asks apps that offer Sign in with Apple to revoke the user's tokens when they unlink it or delete their account.
 * Revoking takes a refresh token, so the authorization code Apple hands the app at sign-in is swapped for one when the
 * Apple ID is linked.
 *
 * Both go to Apple with a client secret: a short-lived JWT signed (ES256) with the app's Sign in with Apple key. Until
 * its team, key ID, key and client ID are all configured, nothing is swapped or revoked, and that is logged once.
 */
final readonly class AppleTokens
{
    public const string TokenUrl = 'https://appleid.apple.com/auth/token';

    public const string RevokeUrl = 'https://appleid.apple.com/auth/revoke';

    private const string UnconfiguredLoggedCacheKey = 'apple-tokens-unconfigured-logged';

    private const int ClientSecretLifetimeSeconds = 300;

    /**
     * Swaps the authorization code for a refresh token of the given Apple ID. Linking never fails over it, so it is null,
     * and why is logged, when Apple turns the code down or can't be reached, or hands back a token for another Apple ID.
     */
    public function refreshToken(string $authorizationCode, string $appleId): ?string
    {
        try {
            $response = $this->post(self::TokenUrl, [
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ]);
        } catch (HttpClientException|NoKeyLoadedException $exception) {
            Log::warning('Could not swap the Sign in with Apple code for a refresh token.', ['error' => $exception->getMessage()]);

            return null;
        }

        if (! $response instanceof Response) {
            return null;
        }

        $refreshToken = $response->json('refresh_token');

        if (! is_string($refreshToken) || $this->subject($response->json('id_token')) !== $appleId) {
            Log::warning('Apple did not hand back a refresh token for the Apple ID being linked.');

            return null;
        }

        return $refreshToken;
    }

    /**
     * Revokes the refresh token, and with it the user's authorization of the app. Whatever goes wrong throws, so the job
     * that revokes it can try again.
     *
     * @throws HttpClientException
     * @throws NoKeyLoadedException
     */
    public function revoke(string $refreshToken): void
    {
        $this->post(self::RevokeUrl, [
            'token' => $refreshToken,
            'token_type_hint' => 'refresh_token',
        ]);
    }

    /**
     * Null without sending anything when the key isn't configured.
     *
     * @param  array<string, string>  $data
     *
     * @throws HttpClientException
     * @throws NoKeyLoadedException
     */
    private function post(string $url, array $data): ?Response
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        return Http::asForm()
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5)
            ->post($url, [
                'client_id' => $credentials['client_id'],
                'client_secret' => $this->clientSecret($credentials),
                ...$data,
            ])
            ->throw();
    }

    /**
     * @return array{client_id: string, team_id: string, key_id: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $credentials = [
            'client_id' => config('services.apple.client_id'),
            'team_id' => config('services.apple.team_id'),
            'key_id' => config('services.apple.key_id'),
            'private_key' => config('services.apple.private_key'),
        ];

        if (array_filter($credentials, fn (mixed $value): bool => is_string($value) && $value !== '') === $credentials) {
            return $credentials;
        }

        if (Cache::add(self::UnconfiguredLoggedCacheKey, true)) {
            Log::warning('Sign in with Apple tokens are not swapped or revoked until APPLE_CLIENT_ID, APPLE_TEAM_ID, APPLE_KEY_ID and APPLE_PRIVATE_KEY are set.');
        }

        return null;
    }

    /**
     * @param  array{client_id: string, team_id: string, key_id: string, private_key: string}  $credentials
     *
     * @throws NoKeyLoadedException
     */
    private function clientSecret(array $credentials): string
    {
        $key = PublicKeyLoader::load($credentials['private_key']);

        throw_unless($key instanceof PrivateKey, NoKeyLoadedException::class, 'The Sign in with Apple key is not an EC private key.');

        $now = now()->getTimestamp();
        $signingInput = $this->encode(['alg' => 'ES256', 'kid' => $credentials['key_id']]).'.'.$this->encode([
            'iss' => $credentials['team_id'],
            'iat' => $now,
            'exp' => $now + self::ClientSecretLifetimeSeconds,
            'aud' => AppleIdentityTokens::Issuer,
            'sub' => $credentials['client_id'],
        ]);

        $signature = $key->withSignatureFormat('IEEE')->withHash('sha256')->sign($signingInput);

        assert(is_string($signature));

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * The `sub` of the ID token Apple hands back with the refresh token. It comes straight from Apple, over TLS, so its
     * claims are read without checking its signature again.
     */
    private function subject(mixed $idToken): ?string
    {
        $claims = json_decode((string) base64_decode(strtr(explode('.', is_string($idToken) ? $idToken : '')[1] ?? '', '-_', '+/'), true), true);

        return is_array($claims) && is_string($claims['sub'] ?? null) ? $claims['sub'] : null;
    }

    /**
     * @param  array<string, int|string>  $data
     */
    private function encode(array $data): string
    {
        return $this->base64UrlEncode((string) json_encode($data));
    }

    private function base64UrlEncode(string $data): string
    {
        return mb_rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
