<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\GifProvider;

/** A GIF picked from a provider; URLs kept exactly as its API returned them. */
final readonly class Gif
{
    public function __construct(
        public GifProvider $provider,
        public string $id,
        public string $url,
        public string $pageUrl,
        public ?string $title,
        public ?string $username,
        public int $width,
        public int $height,
    ) {
        //
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toColumns(): array
    {
        return [
            'provider' => $this->provider->value,
            'provider_gif_id' => $this->id,
            'url' => $this->url,
            'page_url' => $this->pageUrl,
            'title' => $this->title,
            'username' => $this->username,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
