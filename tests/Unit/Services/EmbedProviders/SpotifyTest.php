<?php

declare(strict_types=1);

use App\Enums\EmbedKind;
use App\Services\EmbedProviders\Spotify;
use Uri\WhatWg\Url;

it('embeds tracks, albums, playlists, artists, episodes and shows', function (string $url, string $src, int $height): void {
    $embed = resolve(Spotify::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Spotify')
        ->and($embed?->kind)->toBe(EmbedKind::Audio)
        ->and($embed?->src)->toBe($src)
        ->and($embed?->height)->toBe($height);
})->with([
    'track' => ['https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT?si=abc', 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT', 152],
    'localized track' => ['https://open.spotify.com/intl-de/track/4cOdK2wGLETKBW3PvgPWqT', 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT', 152],
    'episode' => ['https://open.spotify.com/episode/7makk4oTQel546B0PZlDM5', 'https://open.spotify.com/embed/episode/7makk4oTQel546B0PZlDM5', 152],
    'album' => ['https://open.spotify.com/album/1DFixLWuPkv3KT3TnV35m3', 'https://open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3', 352],
    'playlist' => ['https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M/', 'https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M', 352],
    'embed' => ['https://open.spotify.com/embed/artist/0OdUWJ0sBjDrqHygGUXeCF', 'https://open.spotify.com/embed/artist/0OdUWJ0sBjDrqHygGUXeCF', 352],
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Spotify::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://open.spotify.com/',
    'user' => 'https://open.spotify.com/user/spotify',
    'malformed id' => 'https://open.spotify.com/track/nope',
    'other host' => 'https://spotify.com/track/4cOdK2wGLETKBW3PvgPWqT',
]);
