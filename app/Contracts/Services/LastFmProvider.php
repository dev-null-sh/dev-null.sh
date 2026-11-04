<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\Track;
use Illuminate\Http\Client\HttpClientException;

interface LastFmProvider
{
    /**
     * @throws HttpClientException
     */
    public function exists(string $username): bool;

    /**
     * @throws HttpClientException
     */
    public function nowPlaying(string $username): ?Track;
}
