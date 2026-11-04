<?php

declare(strict_types=1);

use App\Contracts\Services\LastFmProvider;
use App\Dto\Track;
use App\Enums\Feature;
use App\Models\User;
use App\Queries\NowPlayingQuery;
use App\Services\Features;
use Illuminate\Http\Client\ConnectionException;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::NowPlaying, true);
});

it('reads what a member is listening to, and caches it for half a minute', function (): void {
    $lastFm = $this->mock(LastFmProvider::class);
    $lastFm->shouldReceive('nowPlaying')->with('RJ')->twice()->andReturn(new Track('Daft Punk', 'Digital Love', 'Discovery', 'https://www.last.fm/music/Daft+Punk/_/Digital+Love'));
    $user = User::factory()->create(['lastfm_username' => 'RJ']);
    $query = resolve(NowPlayingQuery::class);

    $track = ['artist' => 'Daft Punk', 'title' => 'Digital Love', 'album' => 'Discovery', 'url' => 'https://www.last.fm/music/Daft+Punk/_/Digital+Love'];

    expect($query->get($user))->toBe($track);

    $this->travel(29)->seconds();
    expect($query->get($user))->toBe($track);

    $this->travel(2)->seconds();
    $query->get($user);
});

it('remembers that nothing is playing for half a minute', function (): void {
    $lastFm = $this->mock(LastFmProvider::class);
    $lastFm->shouldReceive('nowPlaying')->twice()->andReturnNull();
    $user = User::factory()->create(['lastfm_username' => 'rj']);
    $query = resolve(NowPlayingQuery::class);

    expect($query->get($user))->toBeNull();

    $this->travel(29)->seconds();
    expect($query->get($user))->toBeNull();

    $this->travel(2)->seconds();
    $query->get($user);
});

it('waits five minutes before asking Last.fm again after it failed', function (): void {
    $lastFm = $this->mock(LastFmProvider::class);
    $lastFm->shouldReceive('nowPlaying')->twice()->andThrow(new ConnectionException('Timed out'));
    $user = User::factory()->create(['lastfm_username' => 'rj']);
    $query = resolve(NowPlayingQuery::class);

    expect($query->get($user))->toBeNull();

    $this->travel(299)->seconds();
    expect($query->get($user))->toBeNull();

    $this->travel(2)->seconds();
    $query->get($user);
});

it('is null without asking Last.fm', function (Closure $setUp): void {
    $this->mock(LastFmProvider::class)->shouldNotReceive('nowPlaying');

    expect(resolve(NowPlayingQuery::class)->get($setUp()))->toBeNull();
})->with([
    'no linked account' => fn (): User => User::factory()->create(),
    'feature off' => function (): User {
        resolve(Features::class)->update(Feature::NowPlaying, false);

        return User::factory()->create(['lastfm_username' => 'rj']);
    },
]);
