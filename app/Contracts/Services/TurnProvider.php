<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * A TURN relay, which every call goes through so neither person ever sees the other's IP address. It only forwards
 * the browsers' packets, which stay encrypted between them.
 *
 * @phpstan-type IceServer array{urls: list<string>, username: string, credential: string}
 */
interface TurnProvider
{
    /**
     * Whether calls may only go through the relay, so neither person ever sees the other's IP address.
     */
    public function isRelayOnly(): bool;

    /**
     * Fresh, short-lived relay credentials for one call, never stored.
     *
     * @return list<IceServer>
     */
    public function iceServers(): array;
}
