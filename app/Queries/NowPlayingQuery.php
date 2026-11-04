<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\LastFmProvider;
use App\Enums\Feature;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class NowPlayingQuery
{
    private const int TTL = 30;

    private const int FAILURE_TTL = 300;

    public function __construct(private Features $features, private LastFmProvider $lastFm)
    {
        //
    }

    /**
     * @return array{artist: string, title: string, album: string|null, url: string|null}|null
     */
    public function get(User $user): ?array
    {
        $username = $user->lastfm_username;

        if ($username === null || ! $this->features->enabled(Feature::NowPlaying)) {
            return null;
        }

        $key = 'lastfm-now-playing:'.mb_strtolower($username);

        /** @var array{artist: string, title: string, album: string|null, url: string|null}|false|null $track */
        $track = Cache::get($key);

        if ($track === null) {
            try {
                $track = $this->lastFm->nowPlaying($username)?->toArray() ?? false;
                Cache::put($key, $track, self::TTL);
            } catch (HttpClientException) {
                $track = false;
                Cache::put($key, $track, self::FAILURE_TTL);
            }
        }

        return $track === false ? null : $track;
    }
}
