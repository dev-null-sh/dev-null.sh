<?php

declare(strict_types=1);

use App\Services\PlaylistProviders\AudiusPlaylistProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function audiusTrack(array $overrides = []): array
{
    return [
        'id' => 'emKAp',
        'title' => 'Paradise',
        'duration' => 143,
        'user' => ['handle' => 'broeybeats', 'name' => 'Broey.'],
        'access' => ['stream' => true, 'download' => false],
        'is_delete' => false,
        ...$overrides,
    ];
}

it('reads the tracks of a playlist', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => [[
        'id' => 'nl1bL',
        'playlist_name' => 'lofi Chillhop Beats',
        'tracks' => [
            audiusTrack(),
            audiusTrack(['id' => 'w76Mk', 'title' => ' game over! ', 'duration' => 207, 'user' => ['name' => 'harris cole']]),
        ],
    ]]])]);

    expect(new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi-chillhop-beats-906'))->toBe([
        ['artist' => 'Broey.', 'title' => 'Paradise', 'duration' => 143, 'url' => 'https://api.audius.co/v1/tracks/emKAp/stream?app_name=devnull'],
        ['artist' => 'harris cole', 'title' => 'game over!', 'duration' => 207, 'url' => 'https://api.audius.co/v1/tracks/w76Mk/stream?app_name=devnull'],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.audius.co/v1/resolve?url=https%3A%2F%2Faudius.co%2FByte%2Fplaylist%2Flofi-chillhop-beats-906&app_name=devnull');
});

it('reads albums too', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => [['tracks' => [audiusTrack()]]]])]);

    expect(new AudiusPlaylistProvider()->tracks('https://www.audius.co/Byte/album/snow'))->toHaveCount(1);
});

it('leaves out tracks Winamp cannot play', function (array $track): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => [['tracks' => [audiusTrack(), $track, 'not a track']]]])]);

    expect(new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi'))->toHaveCount(1);
})->with([
    'gated' => [audiusTrack(['access' => ['stream' => false, 'download' => false]])],
    'deleted' => [audiusTrack(['is_delete' => true])],
    'without an id' => [audiusTrack(['id' => null])],
    'without a title' => [audiusTrack(['title' => '  '])],
]);

it('reads the first 200 tracks Winamp can play', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => [['tracks' => [
        audiusTrack(['is_delete' => true]),
        ...array_map(fn (int $index): array => audiusTrack(['id' => "track{$index}"]), range(1, 201)),
    ]]]])]);

    $tracks = new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi');

    expect($tracks)->toHaveCount(200)
        ->and($tracks[0]['url'])->toBe('https://api.audius.co/v1/tracks/track1/stream?app_name=devnull')
        ->and($tracks[199]['url'])->toBe('https://api.audius.co/v1/tracks/track200/stream?app_name=devnull');
});

it('finds no playlist behind a link that is not to one on Audius', function (string $url): void {
    Http::fake();

    expect(new AudiusPlaylistProvider()->tracks($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    'a track' => ['https://audius.co/broeybeats/paradise-51920'],
    'a profile' => ['https://audius.co/Byte'],
    'another site' => ['https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M'],
    'a look-alike host' => ['https://audius.co.example.com/Byte/playlist/lofi'],
    'not over https' => ['http://audius.co/Byte/playlist/lofi'],
    'with a query' => ['https://audius.co/Byte/playlist/lofi?ref=share'],
    'a deeper path' => ['https://audius.co/Byte/playlist/lofi/edit'],
]);

it('finds no playlist Audius does not know', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['code' => 404, 'error' => 'Playlist not found'], 404)]);

    expect(new AudiusPlaylistProvider()->tracks('https://audius.co/nobody/playlist/nothing'))->toBeNull();
});

it('finds no playlist its owner keeps private', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => [['is_private' => true, 'tracks' => [audiusTrack()]]]])]);

    expect(new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi'))->toBeNull();
});

it('finds no tracks in an answer without any', function (): void {
    Http::fake(['api.audius.co/*' => Http::response(['data' => []])]);

    expect(new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi'))->toBe([]);
});

it('fails when Audius is down', function (): void {
    Http::fake(['api.audius.co/*' => Http::response('Bad gateway', 502)]);

    new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi');
})->throws(RequestException::class);

it('fails when Audius cannot be reached', function (): void {
    Http::fake(['api.audius.co/*' => Http::failedConnection()]);

    new AudiusPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi');
})->throws(ConnectionException::class);
