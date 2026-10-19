<?php

declare(strict_types=1);

namespace Tests\Support;

use phpseclib4\Crypt\RSA;
use phpseclib4\Crypt\RSA\PrivateKey;

/**
 * Signs identity tokens the way Sign in with Apple does (RS256), with a key it publishes like Apple's JWKS.
 */
final readonly class AppleTokenIssuer
{
    private function __construct(public string $kid, private PrivateKey $key)
    {
        //
    }

    public static function generate(string $kid = 'apple-test-key'): self
    {
        $key = RSA::createKey(2048);

        return new self($kid, $key);
    }

    /**
     * The same key under another key ID, or another key under the same one.
     */
    public function withKid(string $kid): self
    {
        return new self($kid, $this->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function jwk(): array
    {
        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = json_decode($this->key->getPublicKey()->toString('JWK'), true, flags: JSON_THROW_ON_ERROR);

        return [...$jwks['keys'][0], 'kid' => $this->kid, 'alg' => 'RS256', 'use' => 'sig'];
    }

    /**
     * A token for the raw nonce "raw-nonce", issued just now for the app. A claim given as null is left out.
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $header
     */
    public function token(array $claims = [], array $header = []): string
    {
        $header = [...['alg' => 'RS256', 'kid' => $this->kid], ...$header];
        $claims = array_filter([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.simonhamp.devnull',
            'exp' => now()->addMinutes(10)->getTimestamp(),
            'iat' => now()->getTimestamp(),
            'sub' => '001234.0a1b2c3d4e5f.1234',
            'nonce' => hash('sha256', 'raw-nonce'),
            'email' => 'octocat@privaterelay.appleid.com',
            ...$claims,
        ], fn (mixed $value): bool => $value !== null);

        $signingInput = $this->encode((string) json_encode($header)).'.'.$this->encode((string) json_encode($claims));

        return $signingInput.'.'.$this->encode($this->key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256')->sign($signingInput));
    }

    private function encode(string $data): string
    {
        return mb_rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
