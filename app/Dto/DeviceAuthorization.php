<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\DeviceAuthorizationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A sign-in started from a terminal, the device authorization grant of RFC 8628: the key it was started for, the name
 * the terminal gave itself, the short code the user types on the web, and what the user decided, if they did yet. It
 * only lives in the cache until it expires, under the hash of the device code the terminal polls with, with the hash
 * of the user code pointing at it while nothing is decided.
 */
final readonly class DeviceAuthorization
{
    public const int EXPIRES_AFTER_MINUTES = 15;

    public const int INTERVAL_SECONDS = 5;

    public function __construct(
        public string $key,
        public string $fingerprint,
        public string $deviceName,
        public string $userCode,
        public CarbonImmutable $expiresAt,
        public DeviceAuthorizationStatus $status = DeviceAuthorizationStatus::Pending,
        public ?string $userId = null,
    ) {
        //
    }

    /**
     * The cache key of a device code. Device codes are long and random, so a fast hash is enough.
     */
    public static function keyFor(string $deviceCode): string
    {
        return 'device-authorization:'.hash('sha256', $deviceCode);
    }

    /**
     * The cache key under which a user code points at its authorization, never the code itself.
     */
    public static function userCodeKeyFor(string $userCode): string
    {
        return 'device-authorization-user-code:'.hash('sha256', $userCode);
    }

    /**
     * @param  array<mixed>  $attributes
     */
    public static function fromArray(string $key, array $attributes): self
    {
        $attributes = fluent($attributes);
        $userId = $attributes->get('user_id');

        return new self(
            $key,
            $attributes->string('fingerprint')->value(),
            $attributes->string('device_name')->value(),
            $attributes->string('user_code')->value(),
            CarbonImmutable::createFromTimestamp($attributes->integer('expires_at')),
            DeviceAuthorizationStatus::from($attributes->string('status')->value()),
            is_string($userId) ? $userId : null,
        );
    }

    /**
     * The same sign-in once the user decided on it: approved for them, or denied, in which case it names nobody.
     */
    public function decided(DeviceAuthorizationStatus $status, User $user): self
    {
        return new self(
            $this->key,
            $this->fingerprint,
            $this->deviceName,
            $this->userCode,
            $this->expiresAt,
            $status,
            $status === DeviceAuthorizationStatus::Approved ? $user->id : null,
        );
    }

    /**
     * @return array{fingerprint: string, device_name: string, user_code: string, expires_at: int, status: string, user_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'device_name' => $this->deviceName,
            'user_code' => $this->userCode,
            'expires_at' => $this->expiresAt->getTimestamp(),
            'status' => $this->status->value,
            'user_id' => $this->userId,
        ];
    }
}
