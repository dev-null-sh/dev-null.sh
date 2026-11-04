<?php

declare(strict_types=1);

namespace App\Services\LastFmProviders;

use App\Contracts\Services\LastFmProvider;
use App\Dto\Track;

final readonly class NullLastFmProvider implements LastFmProvider
{
    public function exists(string $username): bool
    {
        return true;
    }

    public function nowPlaying(string $username): ?Track
    {
        return null;
    }
}
