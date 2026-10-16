<?php

declare(strict_types=1);

use App\Enums\EmbedKind;
use App\Services\EmbedProviders\SoundCloud;
use Uri\WhatWg\Url;

it('embeds tracks and sets', function (string $url, string $src, int $height): void {
    $embed = resolve(SoundCloud::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('SoundCloud')
        ->and($embed?->kind)->toBe(EmbedKind::Audio)
        ->and($embed?->src)->toBe($src)
        ->and($embed?->height)->toBe($height);
})->with([
    'track' => ['https://soundcloud.com/forss/flickermood?utm_source=x', 'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fflickermood&auto_play=true&visual=false&show_comments=false', 166],
    'mobile track' => ['https://m.soundcloud.com/forss/flickermood/', 'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fflickermood&auto_play=true&visual=false&show_comments=false', 166],
    'set' => ['https://soundcloud.com/forss/sets/soulhack', 'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fsets%2Fsoulhack&auto_play=true&visual=false&show_comments=false', 450],
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(SoundCloud::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'profile' => 'https://soundcloud.com/forss',
    'profile tab' => 'https://soundcloud.com/forss/likes',
    'discover' => 'https://soundcloud.com/discover/sets',
    'search' => 'https://soundcloud.com/search/sounds',
    'other host' => 'https://example.com/forss/flickermood',
]);
