<?php

declare(strict_types=1);

use App\Enums\EmbedKind;
use App\Services\EmbedProviders\AppleMusic;
use Uri\WhatWg\Url;

it('embeds albums, playlists and songs', function (string $url, string $src, int $height): void {
    $embed = resolve(AppleMusic::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Apple Music')
        ->and($embed?->kind)->toBe(EmbedKind::Audio)
        ->and($embed?->src)->toBe($src)
        ->and($embed?->height)->toBe($height);
})->with([
    'album' => ['https://music.apple.com/us/album/abbey-road-remastered/1441164426', 'https://embed.music.apple.com/us/album/abbey-road-remastered/1441164426', 450],
    'song in an album' => ['https://music.apple.com/us/album/come-together/1441164426?i=1441164430', 'https://embed.music.apple.com/us/album/come-together/1441164426?i=1441164430', 175],
    'song' => ['https://music.apple.com/gb/song/come-together/1441164430', 'https://embed.music.apple.com/gb/song/come-together/1441164430', 175],
    'playlist' => ['https://music.apple.com/us/playlist/todays-hits/pl.f4d106fed2bd41149aaacabb233eb5eb', 'https://embed.music.apple.com/us/playlist/todays-hits/pl.f4d106fed2bd41149aaacabb233eb5eb', 450],
    'album with a malformed song' => ['https://music.apple.com/us/album/1441164426?i=%3Cb%3E', 'https://embed.music.apple.com/us/album/1441164426', 450],
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(AppleMusic::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'browse' => 'https://music.apple.com/us/browse',
    'artist' => 'https://music.apple.com/us/artist/the-beatles/136975',
    'other host' => 'https://apple.com/us/album/abbey-road-remastered/1441164426',
]);
