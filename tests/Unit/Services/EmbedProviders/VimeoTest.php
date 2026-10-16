<?php

declare(strict_types=1);

use App\Services\EmbedProviders\Vimeo;
use Uri\WhatWg\Url;

it('embeds videos', function (string $url, string $src): void {
    $embed = resolve(Vimeo::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Vimeo')
        ->and($embed?->src)->toBe($src)
        ->and($embed?->aspectRatio)->toBe('16 / 9');
})->with([
    'video' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1'],
    'unlisted' => ['https://vimeo.com/76979871/8a7b6c5d4e', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1&h=8a7b6c5d4e'],
    'channel' => ['https://vimeo.com/channels/staffpicks/76979871', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1'],
    'group' => ['https://www.vimeo.com/groups/motion/videos/76979871/', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1'],
    'player' => ['https://player.vimeo.com/video/76979871?h=8a7b6c5d4e', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1&h=8a7b6c5d4e'],
    'player with a malformed hash' => ['https://player.vimeo.com/video/76979871?h=%3Cb%3E', 'https://player.vimeo.com/video/76979871?autoplay=1&dnt=1'],
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Vimeo::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'profile' => 'https://vimeo.com/staff',
    'player home' => 'https://player.vimeo.com/',
    'other host' => 'https://example.com/76979871',
]);
