<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;

final readonly class UpdateTagDescription
{
    public function handle(Tag $tag, ?string $description): Tag
    {
        $tag->forceFill([
            'description' => filled($description) ? mb_trim($description) : null,
        ])->save();

        return $tag->refresh();
    }
}
