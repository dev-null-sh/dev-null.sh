<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class Twitch implements EmbedProvider
{
    private const array RESERVED_PATHS = [
        'directory', 'downloads', 'drops', 'friends', 'inventory', 'jobs', 'login', 'messages', 'p', 'prime', 'search',
        'settings', 'signup', 'store', 'subscriptions', 'turbo', 'videos', 'wallet',
    ];

    public function embed(Url $url): ?Embed
    {
        $host = $url->getAsciiHost();
        $path = mb_rtrim($url->getPath(), '/');

        if ($host === 'clips.twitch.tv' && preg_match('#^/([\w-]+)$#', $path, $matches) === 1) {
            return $this->clip($matches[1]);
        }

        if (! in_array($host, ['twitch.tv', 'www.twitch.tv', 'm.twitch.tv'], true)) {
            return null;
        }

        return match (true) {
            preg_match('#^/videos/(\d+)$#', $path, $matches) === 1 => $this->player(['video' => "v{$matches[1]}"]),
            preg_match('#^/\w+/clip/([\w-]+)$#', $path, $matches) === 1 => $this->clip($matches[1]),
            preg_match('#^/(\w{3,25})$#', $path, $matches) === 1 && ! in_array(mb_strtolower($matches[1]), self::RESERVED_PATHS, true) => $this->player(['channel' => mb_strtolower($matches[1])]),
            default => null,
        };
    }

    private function clip(string $slug): Embed
    {
        return Embed::video('Twitch', 'https://clips.twitch.tv/embed', ['clip' => $slug, 'parent' => $this->parent(), 'autoplay' => 'true']);
    }

    /**
     * @param  array<string, string>  $source
     */
    private function player(array $source): Embed
    {
        return Embed::video('Twitch', 'https://player.twitch.tv/', [...$source, 'parent' => $this->parent(), 'autoplay' => 'true']);
    }

    private function parent(): string
    {
        return (string) Url::parse(config()->string('app.url'))?->getAsciiHost();
    }
}
