<?php

declare(strict_types=1);

use App\Services\EmbedProviders\SlideShare;
use Uri\WhatWg\Url;

it('embeds SlideShare urls', function (string $url, string $src): void {
    $embed = resolve(SlideShare::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('SlideShare')
        ->and($embed?->src)->toBe($src)
        ->and($embed?->aspectRatio)->toBe('17 / 14');
})->with([
    'key' => [
        'https://www.slideshare.net/slideshow/embed_code/key/AbCdEf123',
        'https://www.slideshare.net/slideshow/embed_code/key/AbCdEf123',
    ],
    'numeric embed' => [
        'https://www.slideshare.net/slideshow/embed_code/12345678',
        'https://www.slideshare.net/slideshow/embed_code/12345678',
    ],
    'public slideshow' => [
        'https://www.slideshare.net/slideshow/the-ai-rush/81139551',
        'https://www.slideshare.net/slideshow/embed_code/81139551',
    ],
]);

it('does not embed other SlideShare pages', function (string $url): void {
    expect(resolve(SlideShare::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'profile' => 'https://www.slideshare.net/jeanbaptiste.dumont',
    'home' => 'https://www.slideshare.net/',
    'slug without id' => 'https://www.slideshare.net/slideshow/the-ai-rush',
]);
