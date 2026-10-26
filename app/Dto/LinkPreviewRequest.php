<?php

declare(strict_types=1);

namespace App\Dto;

use Uri\WhatWg\Url;

final readonly class LinkPreviewRequest
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public Url $url,
        public string $address,
        public array $headers,
        public int $maxBodyBytes,
        public float $timeoutSeconds,
    ) {
        //
    }
}
