<?php

declare(strict_types=1);

namespace App\Services\TurnProviders;

use App\Contracts\Services\TurnProvider;

/**
 * No relay at all, for calls between browsers on the same machine while developing: they connect directly, which only
 * ever shares local addresses.
 */
final readonly class LocalTurnProvider implements TurnProvider
{
    public function isRelayOnly(): bool
    {
        return false;
    }

    public function iceServers(): array
    {
        return [];
    }
}
