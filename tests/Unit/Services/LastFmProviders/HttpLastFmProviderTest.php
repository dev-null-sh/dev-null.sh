<?php

declare(strict_types=1);

use App\Dto\Track;
use App\Services\LastFmProviders\HttpLastFmProvider;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.lastfm.key' => 'api-key', 'app.url' => 'https://dev-null.sh']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lastFmTrack(array $overrides = []): array
{
    return [
        'artist' => ['mbid' => '', '#text' => 'Daft Punk'],
        'streamable' => '0',
        'image' => [
            ['size' => 'small', '#text' => 'https://lastfm.freetls.fastly.net/i/u/34s/cover.jpg'],
            ['size' => 'medium', '#text' => 'https://lastfm.freetls.fastly.net/i/u/64s/cover.jpg'],
            ['size' => 'large', '#text' => 'https://lastfm.freetls.fastly.net/i/u/174s/cover.jpg'],
            ['size' => 'extralarge', '#text' => 'https://lastfm.freetls.fastly.net/i/u/300x300/cover.jpg'],
        ],
        'mbid' => '',
        'album' => ['mbid' => '', '#text' => 'Discovery'],
        'name' => 'Digital Love',
        '@attr' => ['nowplaying' => 'true'],
        'url' => 'https://www.last.fm/music/Daft+Punk/_/Digital+Love',
        ...$overrides,
    ];
}

it('finds an account by its username', function (): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response(['user' => ['name' => 'RJ', 'url' => 'https://www.last.fm/user/RJ']])]);

    expect(new HttpLastFmProvider()->exists('rj'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ws.audioscrobbler.com/2.0/?method=user.getinfo&user=rj&api_key=api-key&format=json'
        && $request->header('User-Agent') === ['devnull/1.0 (+https://dev-null.sh)']);
});

it('finds no account for a username nobody has', function (array $body, int $status): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response($body, $status)]);

    expect(new HttpLastFmProvider()->exists('nobody'))->toBeFalse();
})->with([
    'not found' => [['message' => 'User not found', 'error' => 6], 404],
    'not found, but answered' => [['message' => 'User not found', 'error' => 6], 200],
]);

it('fails to look up an account when Last.fm refuses', function (array|string $body, int $status): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response($body, $status)]);

    new HttpLastFmProvider()->exists('rj');
})->with([
    'with an error status' => [['message' => 'Invalid API key', 'error' => 10], 403],
    'while answering' => [['message' => 'Invalid API key', 'error' => 10], 200],
    'not found without saying why' => ['<html>Not Found</html>', 404],
    'answered without the account' => [[], 200],
])->throws(RequestException::class);

it('reads the track playing now', function (): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response(['recenttracks' => ['track' => [
        lastFmTrack(),
        lastFmTrack(['name' => 'Aerodynamic', '@attr' => null, 'date' => ['uts' => '1793800000']]),
    ]]])]);

    expect(new HttpLastFmProvider()->nowPlaying('rj'))->toEqual(new Track(
        artist: 'Daft Punk',
        title: 'Digital Love',
        album: 'Discovery',
        url: 'https://www.last.fm/music/Daft+Punk/_/Digital+Love',
    ));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=rj&api_key=api-key&format=json&limit=1');
});

it('reads the track playing now when it is the only one', function (): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response(['recenttracks' => ['track' => lastFmTrack()]])]);

    expect(new HttpLastFmProvider()->nowPlaying('rj')?->title)->toBe('Digital Love');
});

it('leaves out what is missing or does not point where it should', function (): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response(['recenttracks' => ['track' => [lastFmTrack([
        'album' => ['mbid' => '', '#text' => ' '],
        'url' => 'javascript:alert(1)',
    ])]]])]);

    expect(new HttpLastFmProvider()->nowPlaying('rj'))->toEqual(new Track('Daft Punk', 'Digital Love', null, null));
});

it('is not playing anything', function (array $recentTracks): void {
    Http::fake(['ws.audioscrobbler.com/*' => Http::response(['recenttracks' => $recentTracks])]);

    expect(new HttpLastFmProvider()->nowPlaying('rj'))->toBeNull();
})->with([
    'no tracks' => [['track' => []]],
    'only scrobbled' => [['track' => [lastFmTrack(['@attr' => null, 'date' => ['uts' => '1793800000']])]]],
    'no artist' => [['track' => [lastFmTrack(['artist' => ['mbid' => '', '#text' => '']])]]],
    'no title' => [['track' => [lastFmTrack(['name' => null])]]],
]);

it('fails to read the track playing now when Last.fm cannot be reached', function (Closure|PromiseInterface $response): void {
    Http::fake(['ws.audioscrobbler.com/*' => $response]);

    new HttpLastFmProvider()->nowPlaying('rj');
})->with([
    'hidden' => fn (): PromiseInterface => Http::response(['message' => 'Login: User required to be logged in', 'error' => 17], 403),
    'down' => fn (): PromiseInterface => Http::response([], 503),
    'failing while answering' => fn (): PromiseInterface => Http::response(['message' => 'Operation failed', 'error' => 8], 200),
    'unreachable' => fn (): Closure => Http::failedConnection(),
])->throws(HttpClientException::class);
