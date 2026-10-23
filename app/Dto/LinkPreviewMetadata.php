<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string|null>
 */
final readonly class LinkPreviewMetadata implements Arrayable
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public ?string $siteName = null,
        public ?string $gifUrl = null,
    ) {
        //
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null, gif_url: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'site_name' => $this->siteName,
            'gif_url' => $this->gifUrl,
        ];
    }
}
