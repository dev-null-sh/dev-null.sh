<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Uri\WhatWg\Url;

/**
 * WebAuthn without attestation: the browser sends the public key from getPublicKey(), so no CBOR has to be read.
 */
final readonly class Passkeys
{
    public const int ES256 = -7;

    public const int RS256 = -257;

    private const int TIMEOUT_SECONDS = 300;

    private const int FLAG_USER_PRESENT = 0x01;

    private const int FLAG_USER_VERIFIED = 0x04;

    public static function encode(string $bytes): string
    {
        return mb_rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        $bytes = preg_match('/^[A-Za-z0-9_-]*$/', $value) === 1 ? base64_decode(strtr($value, '-_', '+/'), true) : false;
        throw_unless(is_string($bytes), InvalidArgumentException::class, 'Malformed passkey data.');

        return $bytes;
    }

    /**
     * @return array<string, mixed>
     */
    public function creationOptions(User $user): array
    {
        $challenge = self::encode(random_bytes(32));
        Cache::put("passkey-creation:{$user->id}", $challenge, self::TIMEOUT_SECONDS);

        return [
            'challenge' => $challenge,
            'rp' => ['id' => $this->relyingPartyId(), 'name' => '/dev/null'],
            'user' => ['id' => self::encode($user->id), 'name' => $user->username, 'displayName' => $user->name ?? $user->username],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => self::ES256],
                ['type' => 'public-key', 'alg' => self::RS256],
            ],
            'excludeCredentials' => $this->credentials($user),
            'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'required'],
            'attestation' => 'none',
            'timeout' => self::TIMEOUT_SECONDS * 1000,
        ];
    }

    /**
     * The creation request is spent even when the check fails.
     *
     * @param  array{credential_id: string, client_data_json: string, authenticator_data: string, public_key: string, algorithm: int}  $credential
     * @return array{public_key: string, algorithm: int, sign_count: int}
     */
    public function checkRegistration(User $user, array $credential): array
    {
        $challenge = Cache::pull("passkey-creation:{$user->id}");
        throw_unless(is_string($challenge), InvalidArgumentException::class, 'The passkey request expired. Try again.');

        $this->checkClientData(self::decode($credential['client_data_json']), 'webauthn.create', $challenge);
        $signCount = $this->checkAuthenticatorData(self::decode($credential['authenticator_data']));

        throw_unless(in_array($credential['algorithm'], [self::ES256, self::RS256], true), InvalidArgumentException::class, 'This passkey uses an algorithm /dev/null does not support.');

        $publicKey = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(self::decode($credential['public_key'])), 64, "\n")."-----END PUBLIC KEY-----\n";
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicKey) ?: throw new InvalidArgumentException('The passkey sent an unreadable public key.'));
        $expectedType = $credential['algorithm'] === self::ES256 ? OPENSSL_KEYTYPE_EC : OPENSSL_KEYTYPE_RSA;
        throw_unless(is_array($details) && $details['type'] === $expectedType, InvalidArgumentException::class, 'The passkey key does not match its algorithm.');

        return ['public_key' => $publicKey, 'algorithm' => $credential['algorithm'], 'sign_count' => $signCount];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestOptions(User $user, string $challenge): array
    {
        return [
            'challenge' => $challenge,
            'rpId' => $this->relyingPartyId(),
            'allowCredentials' => $this->credentials($user),
            'userVerification' => 'required',
            'timeout' => self::TIMEOUT_SECONDS * 1000,
        ];
    }

    /**
     * @param  array{nonce?: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string}  $assertion
     */
    public function verify(User $user, array $assertion, string $challenge): Passkey
    {
        $passkey = $user->passkeys()->where('credential_id', $assertion['credential_id'])->first();
        throw_unless($passkey instanceof Passkey, InvalidArgumentException::class, 'That passkey is not one of yours.');

        $signCount = $this->check($passkey->credential['public_key'], $assertion, $challenge);

        // Synced passkeys, such as 1Password's, always count 0, so only a counter that is used and goes back is a copy.
        throw_if(($signCount !== 0 || $passkey->credential['sign_count'] !== 0) && $signCount <= $passkey->credential['sign_count'], InvalidArgumentException::class, 'The passkey counter went back. It may have been copied.');

        $passkey->update(['credential' => [...$passkey->credential, 'sign_count' => $signCount], 'last_used_at' => now()]);

        return $passkey;
    }

    /**
     * Has no side effects, so a stored proof can be checked again at any time.
     *
     * @param  array{client_data_json: string, authenticator_data: string, signature: string, ...}  $assertion
     */
    public function check(string $publicKey, array $assertion, string $challenge): int
    {
        $clientData = self::decode($assertion['client_data_json']);
        $authenticatorData = self::decode($assertion['authenticator_data']);

        $this->checkClientData($clientData, 'webauthn.get', $challenge);
        $signCount = $this->checkAuthenticatorData($authenticatorData);

        throw_unless(
            openssl_verify($authenticatorData.hash('sha256', $clientData, true), self::decode($assertion['signature']), $publicKey, OPENSSL_ALGO_SHA256) === 1,
            InvalidArgumentException::class,
            'The passkey signature is not valid.',
        );

        return $signCount;
    }

    /**
     * @return list<array{type: string, id: string}>
     */
    private function credentials(User $user): array
    {
        /** @var list<string> $ids */
        $ids = $user->passkeys()->pluck('credential_id')->all();

        return array_map(fn (string $id): array => ['type' => 'public-key', 'id' => $id], $ids);
    }

    private function checkClientData(string $json, string $type, string $challenge): void
    {
        $clientData = json_decode($json, true);
        $url = Url::parse(config()->string('app.url'));
        $origin = $url?->getScheme().'://'.$url?->getAsciiHost().($url?->getPort() !== null ? ':'.$url->getPort() : '');

        throw_unless(
            is_array($clientData)
                && ($clientData['type'] ?? null) === $type
                && is_string($clientData['challenge'] ?? null) && hash_equals($challenge, $clientData['challenge'])
                && ($clientData['origin'] ?? null) === $origin
                && ($clientData['crossOrigin'] ?? false) === false,
            InvalidArgumentException::class,
            'The passkey signed something else than /dev/null asked for.',
        );
    }

    /**
     * Returns the signature counter.
     */
    private function checkAuthenticatorData(string $data): int
    {
        throw_unless(mb_strlen($data, '8bit') >= 37, InvalidArgumentException::class, 'Malformed passkey data.');

        $flags = ord($data[32]);

        throw_unless(hash_equals(hash('sha256', $this->relyingPartyId(), true), mb_substr($data, 0, 32, '8bit')), InvalidArgumentException::class, 'The passkey belongs to another site.');
        throw_unless(($flags & self::FLAG_USER_PRESENT) !== 0 && ($flags & self::FLAG_USER_VERIFIED) !== 0, InvalidArgumentException::class, 'The passkey did not verify you.');

        /** @var array{1: int} $counter */
        $counter = unpack('N', mb_substr($data, 33, 4, '8bit'));

        return $counter[1];
    }

    private function relyingPartyId(): string
    {
        return (string) Url::parse(config()->string('app.url'))?->getAsciiHost();
    }
}
