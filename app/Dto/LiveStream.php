<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string|null>
 */
final readonly class LiveStream implements Arrayable
{
    public function __construct(
        public string $url,
        public string $thumbnailUrl,
        public ?string $embedUrl = null,
    ) {
        //
    }

    /**
     * @return array{url: string, thumbnail_url: string, embed_url: string|null}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnailUrl,
            'embed_url' => $this->embedUrl,
        ];
    }
}
