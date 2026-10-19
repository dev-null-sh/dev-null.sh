<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class Vimeo implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        $pattern = match ($url->getAsciiHost()) {
            'vimeo.com', 'www.vimeo.com' => '#^(?:/channels/[\w-]+|/groups/[\w-]+/videos)?/(\d+)(?:/([0-9a-f]+))?$#',
            'player.vimeo.com' => '#^/video/(\d+)$#',
            default => null,
        };

        if ($pattern === null || preg_match($pattern, mb_rtrim($url->getPath(), '/'), $matches) !== 1) {
            return null;
        }

        parse_str($url->getQuery() ?? '', $query);
        $hash = $matches[2] ?? (is_string($query['h'] ?? null) ? $query['h'] : '');

        return Embed::video('Vimeo', "https://player.vimeo.com/video/{$matches[1]}", [
            'autoplay' => 1,
            'dnt' => 1,
            ...(preg_match('/^[0-9a-f]+$/', $hash) === 1 ? ['h' => $hash] : []),
        ]);
    }
}
