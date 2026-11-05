<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Http\Client\HttpClientException;

/**
 * @phpstan-type PlaylistTrack array{artist: string, title: string, duration: int, url: string}
 */
interface PlaylistProvider
{
    /**
     * @return list<PlaylistTrack>|null
     *
     * @throws HttpClientException
     */
    public function tracks(string $url): ?array;
}
