<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Passkeys;
use OpenSSLAsymmetricKey;
use Uri\WhatWg\Url;

/**
 * A passkey in memory: an ES256 key that answers WebAuthn requests the way a browser and authenticator would.
 */
final class SoftwareAuthenticator
{
    public string $credentialId;

    private function __construct(private readonly OpenSSLAsymmetricKey $key, private int $counter)
    {
        $this->credentialId = Passkeys::encode(random_bytes(16));
    }

    /**
     * A counter of 0 that never moves is what synced passkeys, such as 1Password's, report.
     */
    public static function generate(int $counter = 0): self
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        assert($key instanceof OpenSSLAsymmetricKey);

        return new self($key, $counter);
    }

    /**
     * @param  array{challenge: string}  $options
     * @param  array{origin?: string, rp_id?: string, flags?: int, type?: string}  $overrides
     * @return array{credential_id: string, client_data_json: string, authenticator_data: string, public_key: string, algorithm: int}
     */
    public function register(array $options, array $overrides = []): array
    {
        $details = openssl_pkey_get_details($this->key);
        assert(is_array($details) && is_string($details['key']));
        $der = (string) base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $details['key']) ?? '', true);

        return [
            'credential_id' => $this->credentialId,
            'client_data_json' => Passkeys::encode($this->clientData($overrides['type'] ?? 'webauthn.create', $options['challenge'], $overrides)),
            'authenticator_data' => Passkeys::encode($this->authenticatorData($overrides)),
            'public_key' => Passkeys::encode($der),
            'algorithm' => Passkeys::ES256,
        ];
    }

    /**
     * @param  array{challenge: string}  $options
     * @param  array{origin?: string, rp_id?: string, flags?: int, type?: string, challenge?: string}  $overrides
     * @return array{nonce: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string}
     */
    public function sign(string $nonce, array $options, array $overrides = []): array
    {
        $clientData = $this->clientData($overrides['type'] ?? 'webauthn.get', $overrides['challenge'] ?? $options['challenge'], $overrides);
        $authenticatorData = $this->authenticatorData($overrides);

        openssl_sign($authenticatorData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        assert(is_string($signature));

        return [
            'nonce' => $nonce,
            'credential_id' => $this->credentialId,
            'client_data_json' => Passkeys::encode($clientData),
            'authenticator_data' => Passkeys::encode($authenticatorData),
            'signature' => Passkeys::encode($signature),
        ];
    }

    /**
     * @param  array{origin?: string}  $overrides
     */
    private function clientData(string $type, string $challenge, array $overrides): string
    {
        $url = Url::parse(config()->string('app.url'));
        assert($url instanceof Url);

        return (string) json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $overrides['origin'] ?? $url->getScheme().'://'.$url->getAsciiHost().($url->getPort() !== null ? ':'.$url->getPort() : ''),
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array{rp_id?: string, flags?: int}  $overrides
     */
    private function authenticatorData(array $overrides): string
    {
        $rpId = $overrides['rp_id'] ?? (string) Url::parse(config()->string('app.url'))?->getAsciiHost();
        $this->counter = $this->counter === 0 ? 0 : $this->counter + 1;

        return hash('sha256', $rpId, true).chr($overrides['flags'] ?? 0x05).pack('N', $this->counter);
    }
}
