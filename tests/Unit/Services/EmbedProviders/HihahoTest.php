<?php

declare(strict_types=1);

use App\Services\EmbedProviders\Hihaho;
use Uri\WhatWg\Url;

it('embeds interactive videos', function (string $url): void {
    $embed = resolve(Hihaho::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('hihaho')
        ->and($embed?->src)->toBe('https://player.hihaho.com/embed/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b?autoplay=1');
})->with([
    'player' => 'https://player.hihaho.com/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b',
    'embed' => 'https://player.hihaho.com/embed/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b/',
    'uppercase id' => 'https://player.hihaho.com/EDBE72AA-393F-4E40-96F1-CFB3E84D7D4B',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Hihaho::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://player.hihaho.com/',
    'malformed id' => 'https://player.hihaho.com/nope',
    'extra path' => 'https://player.hihaho.com/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b/other',
    'marketing site' => 'https://www.hihaho.com/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b',
    'other host' => 'https://example.com/edbe72aa-393f-4e40-96f1-cfb3e84d7d4b',
]);
