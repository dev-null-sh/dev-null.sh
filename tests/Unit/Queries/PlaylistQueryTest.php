<?php

declare(strict_types=1);

use App\Contracts\Services\PlaylistProvider;
use App\Models\User;
use App\Queries\PlaylistQuery;
use Illuminate\Http\Client\ConnectionException;

$track = ['artist' => 'Broey.', 'title' => 'Paradise', 'duration' => 143, 'url' => 'https://api.audius.co/v1/tracks/emKAp/stream?app_name=devnull'];

it("reads the tracks of a member's playlist once every ten minutes", function () use ($track): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')
        ->with('https://audius.co/Byte/playlist/lofi')
        ->twice()
        ->andReturn([$track]);
    $user = User::factory()->create(['playlist_url' => 'https://audius.co/Byte/playlist/lofi']);
    $playlist = resolve(PlaylistQuery::class);

    expect($playlist->get($user))->toBe([$track]);

    $this->travel(599)->seconds();
    expect($playlist->get($user))->toBe([$track]);

    $this->travel(2)->seconds();
    expect($playlist->get($user))->toBe([$track]);
});

it('shares the tracks between members who share the playlist', function () use ($track): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')->once()->andReturn([$track]);
    $playlist = resolve(PlaylistQuery::class);

    foreach (User::factory(2)->create(['playlist_url' => 'https://audius.co/Byte/playlist/lofi']) as $user) {
        expect($playlist->get($user))->toBe([$track]);
    }
});

it('finds no tracks for a playlist that is gone', function (): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')->andReturnNull();

    expect(resolve(PlaylistQuery::class)->get(User::factory()->create(['playlist_url' => 'https://audius.co/Byte/playlist/gone'])))->toBe([]);
});

it('finds no tracks for a member without a playlist', function (): void {
    $this->mock(PlaylistProvider::class)->shouldNotReceive('tracks');

    expect(resolve(PlaylistQuery::class)->get(User::factory()->create()))->toBe([]);
});

it('waits a minute before asking Audius again after it fails', function () use ($track): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')
        ->twice()
        ->andReturnUsing(fn () => throw new ConnectionException('Timed out'), fn (): array => [$track]);
    $user = User::factory()->create(['playlist_url' => 'https://audius.co/Byte/playlist/lofi']);
    $playlist = resolve(PlaylistQuery::class);

    expect($playlist->get($user))->toBe([]);

    $this->travel(59)->seconds();
    expect($playlist->get($user))->toBe([]);

    $this->travel(2)->seconds();
    expect($playlist->get($user))->toBe([$track]);
});
