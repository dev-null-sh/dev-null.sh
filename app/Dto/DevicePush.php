<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What a phone shows: who did something, never what they wrote, since the text passes through Google and Apple. The
 * data tells the app what to open, and an iPhone threads pushes that share a group.
 */
final readonly class DevicePush
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data,
        public ?string $group = null,
    ) {
        //
    }
}
