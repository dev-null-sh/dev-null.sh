<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * @property-read string $id
 * @property-read string $path
 */
trait IsImageFile
{
    public function mimeType(): string
    {
        return str_ends_with($this->path, '.gif') ? 'image/gif' : 'image/webp';
    }

    public function filename(): string
    {
        return $this->id.($this->mimeType() === 'image/gif' ? '.gif' : '.webp');
    }
}
