<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Dto\Gif;
use App\Enums\GifProvider;

trait HasGifInput
{
    public function gif(): ?Gif
    {
        if (! $this->filled('gif_id')) {
            return null;
        }

        return new Gif(
            GifProvider::from($this->string('gif_provider')->value()),
            $this->string('gif_id')->value(),
            $this->string('gif_url')->value(),
            $this->string('gif_page_url')->value(),
            $this->filled('gif_title') ? $this->string('gif_title')->value() : null,
            $this->filled('gif_username') ? $this->string('gif_username')->value() : null,
            $this->integer('gif_width'),
            $this->integer('gif_height'),
        );
    }
}
