<?php

declare(strict_types=1);

namespace App\Services\PlaylistProviders;

use App\Contracts\Services\PlaylistProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;

final readonly class AudiusPlaylistProvider implements PlaylistProvider
{
    private const string APP_NAME = 'devnull';

    private const int MOST_TRACKS = 200;

    public function tracks(string $url): ?array
    {
        if (preg_match('~\Ahttps://(?:www\.)?audius\.co/[^/?#]+/(?:playlist|album)/[^/?#]+\z~', $url) !== 1) {
            return null;
        }

        $response = Http::acceptJson()->connectTimeout(3)->timeout(5)
            ->get('https://api.audius.co/v1/resolve', ['url' => $url, 'app_name' => self::APP_NAME]);

        if ($response->notFound() || $response->throw()->json('data.0.is_private') === true) {
            return null;
        }

        return array_values($response->collect('data.0.tracks')
            ->map(fn (mixed $track): Fluent => new Fluent(is_array($track) ? $track : []))
            ->filter(fn (Fluent $track): bool => $track->boolean('access.stream')
                && ! $track->boolean('is_delete')
                && $track->string('id')->isNotEmpty()
                && $track->string('title')->trim()->isNotEmpty())
            ->take(self::MOST_TRACKS)
            ->map(fn (Fluent $track): array => [
                'artist' => $track->string('user.name')->trim()->value(),
                'title' => $track->string('title')->trim()->value(),
                'duration' => $track->integer('duration'),
                'url' => 'https://api.audius.co/v1/tracks/'.rawurlencode($track->string('id')->value()).'/stream?app_name='.self::APP_NAME,
            ])
            ->all());
    }
}
