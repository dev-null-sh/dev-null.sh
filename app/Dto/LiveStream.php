<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string>
 */
final readonly class LiveStream implements Arrayable
{
    public function __construct(
        public string $url,
        public string $thumbnailUrl,
    ) {
        //
    }

    /**
     * @return array{url: string, thumbnail_url: string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnailUrl,
        ];
    }
}
