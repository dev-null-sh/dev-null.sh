<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

/**
 * Private files, such as the images and voice notes sent in remotes, are encrypted with AES-256-GCM under a key
 * derived from the app key, and kept as raw bytes: a version byte, the nonce, the tag, then the ciphertext. Unlike
 * Crypt, nothing is base64 encoded, so a file takes no more room encrypted than it would plain. Files written under a
 * key listed in APP_PREVIOUS_KEYS can still be read after rotating it.
 */
final readonly class EncryptedFiles
{
    private const string VERSION = "\x01";

    private const string CIPHER = 'aes-256-gcm';

    public function __construct(private Encrypter $encrypter)
    {
        //
    }

    public function encrypt(string $contents): string
    {
        $nonce = random_bytes(12);
        $ciphertext = openssl_encrypt($contents, self::CIPHER, $this->key($this->encrypter->getKey()), OPENSSL_RAW_DATA, $nonce, $tag, self::VERSION);

        return self::VERSION.$nonce.$tag.$ciphertext;
    }

    public function decrypt(string $file): string
    {
        foreach (array_filter($this->encrypter->getAllKeys(), is_string(...)) as $key) {
            $contents = openssl_decrypt(mb_substr($file, 29, null, '8bit'), self::CIPHER, $this->key($key), OPENSSL_RAW_DATA, mb_substr($file, 1, 12, '8bit'), mb_substr($file, 13, 16, '8bit'), self::VERSION);

            if (is_string($contents) && str_starts_with($file, self::VERSION)) {
                return $contents;
            }
        }

        throw new DecryptException('The file could not be decrypted.');
    }

    /**
     * Files get a key of their own, derived from the app key, so it is never used for two things at once.
     */
    private function key(string $appKey): string
    {
        return hash_hkdf('sha256', $appKey, 32, 'devnull-files');
    }
}
