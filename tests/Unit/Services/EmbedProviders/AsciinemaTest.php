<?php

declare(strict_types=1);

use App\Enums\EmbedKind;
use App\Services\EmbedProviders\Asciinema;
use Uri\WhatWg\Url;

it('embeds recordings', function (string $url): void {
    $embed = resolve(Asciinema::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('asciinema')
        ->and($embed?->kind)->toBe(EmbedKind::Code)
        ->and($embed?->src)->toBe('https://asciinema.org/a/335480/iframe?autoplay=1');
})->with([
    'recording' => 'https://asciinema.org/a/335480',
    'iframe' => 'https://asciinema.org/a/335480/iframe/',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Asciinema::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'explore' => 'https://asciinema.org/explore',
    'other host' => 'https://example.com/a/335480',
]);
