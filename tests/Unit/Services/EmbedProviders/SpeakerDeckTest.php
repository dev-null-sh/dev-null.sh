<?php

declare(strict_types=1);

use App\Services\EmbedProviders\SpeakerDeck;
use Uri\WhatWg\Url;

it('embeds Speaker Deck player urls', function (): void {
    $embed = resolve(SpeakerDeck::class)->embed(Url::parse('https://speakerdeck.com/player/abc123XYZ'));

    expect($embed?->provider)->toBe('Speaker Deck')
        ->and($embed?->src)->toBe('https://speakerdeck.com/player/abc123XYZ')
        ->and($embed?->aspectRatio)->toBe('16 / 9');
});

it('does not embed public deck pages without a player id', function (): void {
    expect(resolve(SpeakerDeck::class)->embed(Url::parse('https://speakerdeck.com/example/shipping-faster')))->toBeNull();
});
