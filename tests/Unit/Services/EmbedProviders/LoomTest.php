<?php

declare(strict_types=1);

use App\Services\EmbedProviders\Loom;
use Uri\WhatWg\Url;

it('embeds shared videos', function (string $url): void {
    $embed = resolve(Loom::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Loom')
        ->and($embed?->src)->toBe('https://www.loom.com/embed/e5b8c04bca094dd8a5507925ab887002?autoplay=1');
})->with([
    'share' => 'https://www.loom.com/share/e5b8c04bca094dd8a5507925ab887002',
    'embed' => 'https://loom.com/embed/e5b8c04bca094dd8a5507925ab887002/',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Loom::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://www.loom.com/',
    'malformed id' => 'https://www.loom.com/share/nope',
    'other host' => 'https://example.com/share/e5b8c04bca094dd8a5507925ab887002',
]);
