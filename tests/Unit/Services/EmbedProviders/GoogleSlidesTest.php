<?php

declare(strict_types=1);

use App\Services\EmbedProviders\GoogleSlides;
use Uri\WhatWg\Url;

it('embeds published Google Slides', function (string $url, string $src): void {
    $embed = resolve(GoogleSlides::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Google Slides')
        ->and($embed?->src)->toBe($src);
})->with([
    'embed' => [
        'https://docs.google.com/presentation/d/abc123/embed',
        'https://docs.google.com/presentation/d/abc123/embed?start=false&loop=false',
    ],
    'pub' => [
        'https://docs.google.com/presentation/d/e/2PACX-1abc/pub',
        'https://docs.google.com/presentation/d/e/2PACX-1abc/embed?start=false&loop=false',
    ],
]);

it('does not embed edit links', function (): void {
    expect(resolve(GoogleSlides::class)->embed(Url::parse('https://docs.google.com/presentation/d/abc123/edit')))->toBeNull();
});
