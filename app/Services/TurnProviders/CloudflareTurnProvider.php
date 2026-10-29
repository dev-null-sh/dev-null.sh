<?php

declare(strict_types=1);

namespace App\Services\TurnProviders;

use App\Contracts\Services\TurnProvider;
use Illuminate\Support\Facades\Http;

/**
 * @phpstan-import-type IceServer from TurnProvider
 */
final readonly class CloudflareTurnProvider implements TurnProvider
{
    /**
     * Long enough for a long call, as the relay drops a call once its credentials expire.
     */
    private const int TTL = 14_400;

    public function isAvailable(): bool
    {
        return filled(config('services.cloudflare_turn.key_id')) && filled(config('services.cloudflare_turn.api_token'));
    }

    public function isRelayOnly(): bool
    {
        return true;
    }

    /**
     * The relay's own ports only: browsers block port 53, and the STUN entries would reveal the address the relay is
     * there to hide.
     *
     * @return list<IceServer>
     */
    public function iceServers(): array
    {
        /** @var list<array{urls: list<string>|string, username?: string, credential?: string}> $servers */
        $servers = Http::withToken(config()->string('services.cloudflare_turn.api_token'))
            ->timeout(5)
            ->post('https://rtc.live.cloudflare.com/v1/turn/keys/'.config()->string('services.cloudflare_turn.key_id').'/credentials/generate-ice-servers', ['ttl' => self::TTL])
            ->throw()
            ->json('iceServers');

        return array_values(collect($servers)
            ->filter(fn (array $server): bool => isset($server['username'], $server['credential']))
            ->map(fn (array $server): array => [
                'urls' => array_values(collect((array) $server['urls'])->reject(fn (string $url): bool => preg_match('/:53(\?|$)/', $url) === 1)->all()),
                'username' => (string) $server['username'],
                'credential' => (string) $server['credential'],
            ])
            ->all());
    }
}
