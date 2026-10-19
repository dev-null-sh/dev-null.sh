<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A Mastodon account on another server, signing requests the way Mastodon does.
 */
final readonly class RemoteActor
{
    private function __construct(public string $id, public string $publicKey, private string $privateKey)
    {
        //
    }

    public static function generate(string $id = 'https://mastodon.example/users/alice'): self
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        assert(is_array($details) && is_string($details['key']) && is_string($privateKey));

        return new self($id, $details['key'], $privateKey);
    }

    /**
     * Checks a request the way Mastodon checks a delivery from /dev/null.
     *
     * @param  array<string, list<string>>  $headers
     */
    public static function verifies(string $publicKey, string $method, string $path, array $headers): bool
    {
        preg_match_all('/(\w+)="([^"]*)"/', $headers['Signature'][0], $matches, PREG_SET_ORDER);
        $parameters = array_column($matches, 2, 1);

        $signingString = implode("\n", array_map(
            fn (string $header): string => $header === '(request-target)'
                ? '(request-target): '.mb_strtolower($method).' '.$path
                : $header.': '.$headers[ucfirst($header)][0],
            explode(' ', $parameters['headers']),
        ));

        return openssl_verify($signingString, (string) base64_decode($parameters['signature'], true), $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function keyId(): string
    {
        return $this->id.'#main-key';
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return [
            'id' => $this->id,
            'type' => 'Person',
            'inbox' => $this->id.'/inbox',
            'endpoints' => ['sharedInbox' => 'https://mastodon.example/inbox'],
            'publicKey' => ['id' => $this->keyId(), 'owner' => $this->id, 'publicKeyPem' => $this->publicKey],
        ];
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    public function follow(string $object, array $activity = []): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->id.'#follows/1',
            'type' => 'Follow',
            'actor' => $this->id,
            'object' => $object,
            ...$activity,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sign(string $path, string $host, string $body, string $date = '', ?string $digest = null, string $headers = '(request-target) host date digest', string $algorithm = 'rsa-sha256'): array
    {
        $date = $date === '' ? gmdate('D, d M Y H:i:s \G\M\T') : $date;
        $digest ??= 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));
        $values = ['(request-target)' => 'post '.$path, 'host' => $host, 'date' => $date, 'digest' => $digest];

        $signingString = implode("\n", array_map(
            fn (string $header): string => $header.': '.$values[$header],
            explode(' ', $headers),
        ));

        openssl_sign($signingString, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        assert(is_string($signature));

        return [
            'Host' => $host,
            'Date' => $date,
            'Digest' => $digest,
            'Signature' => sprintf('keyId="%s",algorithm="%s",headers="%s",signature="%s"', $this->keyId(), $algorithm, $headers, base64_encode($signature)),
        ];
    }
}
