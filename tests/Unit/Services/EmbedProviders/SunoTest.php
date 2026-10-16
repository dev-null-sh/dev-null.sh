<?php

declare(strict_types=1);

use App\Enums\EmbedKind;
use App\Services\EmbedProviders\Suno;
use Uri\WhatWg\Url;

it('embeds songs', function (string $url): void {
    $embed = resolve(Suno::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Suno')
        ->and($embed?->kind)->toBe(EmbedKind::Audio)
        ->and($embed?->src)->toBe('https://suno.com/embed/c0cdb3ad-f49d-4125-8e16-4d7dd7924a02')
        ->and($embed?->height)->toBe(240);
})->with([
    'song' => 'https://suno.com/song/c0cdb3ad-f49d-4125-8e16-4d7dd7924a02',
    'song with www' => 'https://www.suno.com/song/c0cdb3ad-f49d-4125-8e16-4d7dd7924a02/',
    'legacy app' => 'https://app.suno.ai/song/C0CDB3AD-F49D-4125-8E16-4D7DD7924A02',
    'embed' => 'https://suno.com/embed/c0cdb3ad-f49d-4125-8e16-4d7dd7924a02',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Suno::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://suno.com/',
    'profile' => 'https://suno.com/@someone',
    'short link' => 'https://suno.com/s/AbCdEf',
    'malformed id' => 'https://suno.com/song/nope',
    'other host' => 'https://example.com/song/c0cdb3ad-f49d-4125-8e16-4d7dd7924a02',
]);
