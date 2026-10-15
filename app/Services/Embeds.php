<?php

declare(strict_types=1);

namespace App\Services;

use Uri\WhatWg\Url;

final readonly class Embeds
{
    private const string DEFAULT_RATIO = '1:1';

    private const float MIN_RATIO = 1 / 4;

    private const float MAX_RATIO = 4;

    /**
     * Splits the "/embed <url> [<width>:<height>]" command that may end a body off it. Returns null when the body
     * doesn't end with the command, a null url when it does but the url can't be embedded, and a null ratio when the
     * ratio isn't one we allow. Without a ratio the embed is square. Keep the rules in sync with
     * resources/js/lib/embed.ts.
     *
     * @return array{body: string, url: string|null, ratio: string|null}|null
     */
    public function extract(string $body): ?array
    {
        if (preg_match('/\A(?<body>.*?)(?<!\S)\/embed[ \t]+(?<url>\S+)(?:[ \t]+(?<ratio>\S+))?\s*\z/su', $body, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        return [
            'body' => mb_rtrim($matches['body']),
            'url' => $this->embeddableUrl($matches['url']),
            'ratio' => $this->embeddableRatio($matches['ratio'] ?? self::DEFAULT_RATIO),
        ];
    }

    /**
     * Only https pages without credentials on another host than this site can be embedded. The frame's sandbox lets
     * the page keep its own origin, so it must never share ours.
     */
    private function embeddableUrl(string $candidate): ?string
    {
        $url = Url::parse($candidate);

        if ($url?->getScheme() !== 'https'
            || ($url->getUsername() ?? '') !== ''
            || ($url->getPassword() ?? '') !== ''
            || $url->getAsciiHost() === Url::parse(config()->string('app.url'))?->getAsciiHost()) {
            return null;
        }

        return $url->toAsciiString();
    }

    /**
     * A width and height of up to three digits each, from 1:4 (tall) to 4:1 (wide).
     */
    private function embeddableRatio(string $candidate): ?string
    {
        if (preg_match('/\A(?<width>[1-9]\d{0,2}):(?<height>[1-9]\d{0,2})\z/', $candidate, $matches) !== 1) {
            return null;
        }

        $ratio = (int) $matches['width'] / (int) $matches['height'];

        return $ratio >= self::MIN_RATIO && $ratio <= self::MAX_RATIO ? $candidate : null;
    }
}
