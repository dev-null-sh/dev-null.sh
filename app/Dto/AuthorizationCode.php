<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * The one-time code the web hands the devnull app once the user approves it, for the app to redeem for a device. It is
 * bound to the user, the key, the device name and the app's PKCE challenge, and only lives in the cache for a few
 * minutes, under the hash of the code rather than the code itself.
 */
final readonly class AuthorizationCode
{
    public const int EXPIRES_AFTER_MINUTES = 5;

    public function __construct(
        public string $key,
        public string $userId,
        public string $fingerprint,
        public string $deviceName,
        public string $codeChallenge,
    ) {
        //
    }

    /**
     * The cache key of a code. Codes are long and random, so a fast hash is enough.
     */
    public static function keyFor(string $code): string
    {
        return 'authorization-code:'.hash('sha256', $code);
    }

    /**
     * @param  array<mixed>  $attributes
     */
    public static function fromArray(string $key, array $attributes): self
    {
        $attributes = fluent($attributes);

        return new self(
            $key,
            $attributes->string('user_id')->value(),
            $attributes->string('fingerprint')->value(),
            $attributes->string('device_name')->value(),
            $attributes->string('code_challenge')->value(),
        );
    }

    /**
     * The PKCE S256 challenge for a verifier: base64url(sha256(verifier)), without padding.
     */
    public static function challengeFor(string $verifier): string
    {
        return mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @return array{user_id: string, fingerprint: string, device_name: string, code_challenge: string}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'fingerprint' => $this->fingerprint,
            'device_name' => $this->deviceName,
            'code_challenge' => $this->codeChallenge,
        ];
    }
}
