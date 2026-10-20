<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\LiveStream;

interface LivePlatformDriver
{
    public function accepts(string $url): bool;

    /**
     * The stream an accepted link points to, worked out from the link alone.
     */
    public function stream(string $url): LiveStream;
}
