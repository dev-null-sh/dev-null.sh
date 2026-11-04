<?php

declare(strict_types=1);

namespace App\Services\LastFmProviders;

use App\Contracts\Services\LastFmProvider;
use App\Dto\Track;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class HttpLastFmProvider implements LastFmProvider
{
    private const int USER_NOT_FOUND = 6;

    public function exists(string $username): bool
    {
        $response = $this->get('user.getinfo', $username);

        if ($response->json('error') === self::USER_NOT_FOUND) {
            return false;
        }

        throw_unless(is_string($this->successful($response)->json('user.name')), RequestException::class, $response);

        return true;
    }

    public function nowPlaying(string $username): ?Track
    {
        $tracks = $this->successful($this->get('user.getrecenttracks', $username, ['limit' => 1]))->json('recenttracks.track');
        $track = is_array($tracks) && array_is_list($tracks) ? ($tracks[0] ?? null) : $tracks;

        if (! is_array($track) || data_get($track, '@attr.nowplaying') !== 'true') {
            return null;
        }

        $artist = $this->text(data_get($track, 'artist.#text'));
        $title = $this->text(data_get($track, 'name'));

        if ($artist === null || $title === null) {
            return null;
        }

        return new Track(
            artist: $artist,
            title: $title,
            album: $this->text(data_get($track, 'album.#text')),
            url: $this->link(data_get($track, 'url')),
        );
    }

    /**
     * @param  array<string, int>  $query
     */
    private function get(string $method, string $username, array $query = []): Response
    {
        $key = config('services.lastfm.key');
        assert(is_string($key));

        $appUrl = config('app.url');
        assert(is_string($appUrl));

        return Http::withUserAgent("devnull/1.0 (+{$appUrl})")
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5)
            ->get('https://ws.audioscrobbler.com/2.0/', [
                'method' => $method,
                'user' => $username,
                'api_key' => $key,
                'format' => 'json',
                ...$query,
            ]);
    }

    private function successful(Response $response): Response
    {
        throw_if($response->json('error') !== null, RequestException::class, $response);

        return $response->throw();
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    private function link(mixed $value): ?string
    {
        return is_string($value) && str_starts_with($value, 'https://www.last.fm/') ? $value : null;
    }
}
