<?php

declare(strict_types=1);

namespace App\Dto;

use InvalidArgumentException;
use phpseclib4\Common\Functions\Strings;
use phpseclib4\Crypt\Common\PublicKey;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Crypt\RSA;
use phpseclib4\Exception\BaseException;

final readonly class SshPublicKey
{
    private const array SupportedTypes = ['ssh-ed25519', 'ssh-rsa'];

    private const int MinimumRsaBits = 2048;

    private function __construct(
        public string $type,
        public string $blob,
        private PublicKey $key,
    ) {
        //
    }

    /**
     * Parses a public key in the `authorized_keys` format: "<type> <base64> [comment]".
     */
    public static function fromOpenSsh(string $line): self
    {
        $parts = preg_split('/\s+/', mb_trim($line)) ?: [];

        $blob = base64_decode($parts[1] ?? '', true);

        throw_if($blob === false || $blob === '', InvalidArgumentException::class, 'Malformed SSH public key.');

        $key = self::fromBlob($blob);

        throw_if($key->type !== $parts[0], InvalidArgumentException::class, 'Malformed SSH public key.');

        return $key;
    }

    /**
     * Same as fromOpenSsh(), but returns null for malformed or unsupported keys.
     */
    public static function tryFromOpenSsh(string $line): ?self
    {
        try {
            return self::fromOpenSsh($line);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Parses a public key in the SSH binary format, as embedded in signatures.
     */
    public static function fromBlob(string $blob): self
    {
        try {
            $data = $blob;
            [$type] = Strings::unpackSSH2('s', $data);
        } catch (BaseException) {
            throw new InvalidArgumentException('Malformed SSH public key.');
        }

        throw_unless(is_string($type), InvalidArgumentException::class, 'Malformed SSH public key.');
        throw_unless(in_array($type, self::SupportedTypes, true), InvalidArgumentException::class, "Unsupported SSH key type [{$type}]. Use an Ed25519 or RSA key.");

        try {
            $key = PublicKeyLoader::loadPublicKey("{$type} ".base64_encode($blob));
        } catch (BaseException) {
            throw new InvalidArgumentException('Malformed SSH public key.');
        }

        // Only accept the canonical encoding, so a key always has a single fingerprint.
        throw_if($key->toString('OpenSSH', ['binary' => true]) !== $blob, InvalidArgumentException::class, 'Malformed SSH public key.');

        throw_if(
            $key instanceof RSA\PublicKey && $key->getLength() < self::MinimumRsaBits,
            InvalidArgumentException::class,
            'RSA keys must be at least '.self::MinimumRsaBits.' bits.',
        );

        return new self($type, $blob, $key);
    }

    public function fingerprint(): string
    {
        return 'SHA256:'.$this->key->getFingerprint('sha256');
    }

    public function toOpenSsh(): string
    {
        return $this->type.' '.base64_encode($this->blob);
    }

    /**
     * Verifies an SSH signature blob ("<algorithm> <signature>") over the given data.
     */
    public function verify(string $data, string $signature): bool
    {
        try {
            [$algorithm, $bytes] = Strings::unpackSSH2('ss', $signature);
        } catch (BaseException) {
            return false;
        }

        if ($signature !== '' || ! is_string($algorithm) || ! is_string($bytes)) {
            return false;
        }

        $key = match (true) {
            $this->type === 'ssh-ed25519' && $algorithm === 'ssh-ed25519' => $this->key,
            $this->key instanceof RSA\PublicKey && $algorithm === 'rsa-sha2-256' => $this->key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256'),
            $this->key instanceof RSA\PublicKey && $algorithm === 'rsa-sha2-512' => $this->key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha512'),
            default => null,
        };

        return $key?->verify($data, $bytes) ?? false;
    }
}
