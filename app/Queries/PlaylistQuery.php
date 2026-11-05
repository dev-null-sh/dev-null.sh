<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\PlaylistProvider;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-import-type PlaylistTrack from PlaylistProvider
 */
final readonly class PlaylistQuery
{
    private const int TTL = 600;

    private const int FAILURE_TTL = 60;

    public function __construct(private PlaylistProvider $playlists)
    {
        //
    }

    /**
     * @return list<PlaylistTrack>
     */
    public function get(User $user): array
    {
        $url = $user->playlist_url;

        if ($url === null) {
            return [];
        }

        $key = 'playlist:'.hash('xxh128', $url);

        try {
            return Cache::remember($key, self::TTL, fn (): array => $this->playlists->tracks($url) ?? []);
        } catch (HttpClientException) {
            Cache::put($key, [], self::FAILURE_TTL);

            return [];
        }
    }
}
