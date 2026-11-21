<?php

declare(strict_types=1);

use App\Actions\CreateLinkPreview;
use App\Models\LinkPreview;

it('shares one preview between the ways of writing the same link, under its canonical url', function (): void {
    $action = resolve(CreateLinkPreview::class);

    $first = $action->handle('https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=share');
    $second = $action->handle('https://youtu.be/dQw4w9WgXcQ');

    expect($second->id)->toBe($first->id)
        ->and(LinkPreview::query()->count())->toBe(1)
        ->and($first->refresh())
        ->url->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->url_hash->toBe(hash('sha256', 'https://youtu.be/dQw4w9WgXcQ'));
});
