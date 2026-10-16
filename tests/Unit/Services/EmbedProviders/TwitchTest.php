<?php

declare(strict_types=1);

use App\Services\EmbedProviders\Twitch;
use Uri\WhatWg\Url;

beforeEach(function (): void {
    config(['app.url' => 'https://dev-null.sh']);
});

it('embeds channels, videos and clips', function (string $url, string $src): void {
    $embed = resolve(Twitch::class)->embed(Url::parse($url));

    expect($embed?->provider)->toBe('Twitch')
        ->and($embed?->src)->toBe($src);
})->with([
    'channel' => ['https://www.twitch.tv/ThePrimeagen', 'https://player.twitch.tv/?channel=theprimeagen&parent=dev-null.sh&autoplay=true'],
    'video' => ['https://www.twitch.tv/videos/2245012345', 'https://player.twitch.tv/?video=v2245012345&parent=dev-null.sh&autoplay=true'],
    'clip' => ['https://www.twitch.tv/theprimeagen/clip/AwkwardHelplessSalamanderSwiftRage-abc', 'https://clips.twitch.tv/embed?clip=AwkwardHelplessSalamanderSwiftRage-abc&parent=dev-null.sh&autoplay=true'],
    'clip link' => ['https://clips.twitch.tv/AwkwardHelplessSalamanderSwiftRage', 'https://clips.twitch.tv/embed?clip=AwkwardHelplessSalamanderSwiftRage&parent=dev-null.sh&autoplay=true'],
]);

it('does not embed other pages', function (string $url): void {
    expect(resolve(Twitch::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://www.twitch.tv/',
    'directory' => 'https://www.twitch.tv/directory',
    'settings' => 'https://www.twitch.tv/Settings',
    'channel videos' => 'https://www.twitch.tv/theprimeagen/videos',
    'clips home' => 'https://clips.twitch.tv/',
    'other host' => 'https://example.com/theprimeagen',
]);
