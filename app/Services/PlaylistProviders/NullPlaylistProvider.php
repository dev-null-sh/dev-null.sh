<?php

declare(strict_types=1);

namespace App\Services\PlaylistProviders;

use App\Contracts\Services\PlaylistProvider;

final readonly class NullPlaylistProvider implements PlaylistProvider
{
    public function tracks(string $url): array
    {
        return [];
    }
}
