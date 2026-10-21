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

it('embeds live streams', function (string $url): void {
    $embed = resolve(Asciinema::class)->embed(Url::parse($url));

    expect($embed?->toArray())->toBe([
        'provider' => 'asciinema',
        'kind' => 'code',
        'src' => 'https://asciinema.org/s/xFME13DLJdggX0Vt',
        'aspect_ratio' => null,
        'height' => 420,
        'poster' => null,
        'renderer' => 'asciinema-stream',
        'stream_url' => 'wss://asciinema.org/ws/s/xFME13DLJdggX0Vt',
    ]);
})->with([
    'live stream' => 'https://asciinema.org/s/xFME13DLJdggX0Vt',
    'trailing slash' => 'https://asciinema.org/s/xFME13DLJdggX0Vt/',
    'query string' => 'https://asciinema.org/s/xFME13DLJdggX0Vt?theme=monokai',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Asciinema::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'explore' => 'https://asciinema.org/explore',
    'other host' => 'https://example.com/a/335480',
    'other host live stream' => 'https://example.com/s/xFME13DLJdggX0Vt',
    'subdomain live stream' => 'https://www.asciinema.org/s/xFME13DLJdggX0Vt',
    'malformed token' => 'https://asciinema.org/s/xFME13DLJdggX0Vt_foo',
    'extra live stream path' => 'https://asciinema.org/s/xFME13DLJdggX0Vt/iframe',
]);
