<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * The parts of a Firebase service account key that pushing needs. The rest of the key is the same in every account
 * Google hands out, so it stays in config/services.php.
 */
final readonly class FcmCredentials
{
    private function __construct(
        public string $projectId,
        public string $clientEmail,
        public string $privateKey,
        public string $tokenUri,
    ) {
        //
    }

    /**
     * Null when no service account is configured, in which case no phone is pushed to.
     */
    public static function fromConfig(): ?self
    {
        $credentials = config('services.fcm.credentials');

        if (! is_array($credentials)) {
            return null;
        }

        $projectId = $credentials['project_id'] ?? null;
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;
        $tokenUri = $credentials['token_uri'] ?? null;

        if (! is_string($projectId) || $projectId === ''
            || ! is_string($clientEmail) || $clientEmail === ''
            || ! is_string($privateKey) || $privateKey === ''
            || ! is_string($tokenUri) || $tokenUri === '') {
            return null;
        }

        return new self($projectId, $clientEmail, $privateKey, $tokenUri);
    }
}
