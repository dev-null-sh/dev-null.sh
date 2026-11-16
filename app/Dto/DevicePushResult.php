<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What became of one phone's push: whether it should be tried again, and, when the service said so, how long to wait
 * first.
 */
final readonly class DevicePushResult
{
    private function __construct(
        public bool $retry,
        public ?int $retryAfterSeconds,
    ) {
        //
    }

    /**
     * Delivered, or refused in a way that another try cannot fix.
     */
    public static function settled(): self
    {
        return new self(false, null);
    }

    public static function retry(?int $retryAfterSeconds = null): self
    {
        return new self(true, $retryAfterSeconds);
    }
}
