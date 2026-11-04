<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string|null>
 */
final readonly class Track implements Arrayable
{
    public function __construct(
        public string $artist,
        public string $title,
        public ?string $album,
        public ?string $url,
    ) {
        //
    }

    /**
     * @return array{artist: string, title: string, album: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'artist' => $this->artist,
            'title' => $this->title,
            'album' => $this->album,
            'url' => $this->url,
        ];
    }
}
