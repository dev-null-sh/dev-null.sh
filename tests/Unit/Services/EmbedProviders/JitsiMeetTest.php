<?php

declare(strict_types=1);

use App\Services\EmbedProviders\JitsiMeet;
use Uri\WhatWg\Url;

it('embeds rooms as calls', function (string $url): void {
    expect(resolve(JitsiMeet::class)->embed(Url::parse($url))?->toArray())->toBe([
        'provider' => 'Jitsi Meet',
        'kind' => 'call',
        'src' => 'https://meet.jit.si/DevNull_Standup-42',
        'aspect_ratio' => null,
        'height' => null,
        'poster' => null,
    ]);
})->with([
    'room' => 'https://meet.jit.si/DevNull_Standup-42',
    'trailing slash' => 'https://meet.jit.si/DevNull_Standup-42/',
    'query and fragment' => 'https://meet.jit.si/DevNull_Standup-42?jwt=secret#config.startWithAudioMuted=true',
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(JitsiMeet::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://meet.jit.si/',
    'script' => 'https://meet.jit.si/external_api.js',
    'static page' => 'https://meet.jit.si/static/close.html',
    'other jitsi host' => 'https://8x8.vc/DevNull_Standup-42',
    'other host' => 'https://example.com/DevNull_Standup-42',
]);
