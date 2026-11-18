<?php

declare(strict_types=1);

namespace App\Services;

use Uri\WhatWg\Url;

final readonly class CanonicalUrls
{
    /**
     * Parameters that only track where a link was shared, on every site, besides any starting with "utm_", whatever
     * their case.
     *
     * @var list<string>
     */
    private const array TRACKING_PARAMETERS = ['fbclid', 'gclid', 'mc_cid', 'mc_eid'];

    /**
     * Sites served under several hosts, by a pattern of their hosts: the host they are shared under (null keeps the
     * host), and their own share and tracking parameters.
     *
     * @var array<string, array{0: string|null, 1: list<string>}>
     */
    private const array SITES = [
        '(www\.|m\.)?youtube\.com' => ['youtube.com', ['si', 'feature', 'is', 'pp']],
        'youtu\.be' => [null, ['si', 'feature', 'is', 'pp']],
        '(www\.|mobile\.)?(twitter|x)\.com' => ['x.com', ['s', 't', 'ref', 'ref_src']],
        '(www\.)?instagram\.com' => ['instagram.com', ['igsh', 'igshid', 'srtk', 'exln']],
        '(www\.|[a-z]{2}\.)?linkedin\.com' => ['linkedin.com', []],
        '(www\.|old\.|new\.)?reddit\.com' => ['reddit.com', []],
        '(www\.)?producthunt\.com' => ['producthunt.com', []],
        '(www\.)?php\.net' => ['php.net', []],
        '(www\.)?twitch\.tv' => ['twitch.tv', []],
        '(www\.)?meetup\.com' => ['meetup.com', []],
        'open\.spotify\.com' => [null, ['si']],
        '(drive|docs)\.google\.com' => [null, ['usp']],
        '(www\.)?alibabacloud\.com' => [null, ['spm']],
    ];

    private const string YOUTUBE_VIDEO_ID = '[A-Za-z0-9_-]{11}';

    /**
     * The one URL that every way of writing a link to the same page shares: a lowercase host without its default port,
     * no fragment, no tracking parameters, uppercase percent-escapes, no path when it is only "/", and the shortest
     * URL of the sites in SITES. A URL that isn't http or https is returned as is.
     */
    public function canonical(string $url): string
    {
        $parsed = Url::parse($url);

        if (! $parsed instanceof Url || ! in_array($parsed->getScheme(), ['http', 'https'], true)) {
            return $url;
        }

        $host = (string) $parsed->getUnicodeHost();
        $path = $this->withUppercaseEscapes($parsed->getPath());
        $parameters = collect(explode('&', $this->withUppercaseEscapes($parsed->getQuery() ?? '')))
            ->reject(fn (string $parameter): bool => $parameter === '' || str_starts_with($this->name($parameter), 'utm_') || in_array($this->name($parameter), self::TRACKING_PARAMETERS, true));

        foreach (self::SITES as $pattern => [$siteHost, $siteParameters]) {
            if (preg_match("/^{$pattern}$/", $host) === 1) {
                $host = $siteHost ?? $host;
                $parameters = $parameters->reject(fn (string $parameter): bool => in_array($this->name($parameter), $siteParameters, true));

                break;
            }
        }

        $video = $host === 'youtube.com' && $path === '/watch'
            ? $parameters->first(fn (string $parameter): bool => preg_match('/^v='.self::YOUTUBE_VIDEO_ID.'$/', $parameter) === 1)
            : null;

        if (is_string($video)) {
            [$host, $path] = ['youtu.be', '/'.mb_substr($video, 2)];
            $parameters = $parameters->reject(fn (string $parameter): bool => $this->name($parameter) === 'v');
        }

        $canonical = $parsed->withHost($host)->withPath($path)->withQuery($parameters->isEmpty() ? null : $parameters->implode('&'))->withFragment(null)->toUnicodeString();

        return $path === '/' ? (string) preg_replace('#/(?=\?|$)#', '', $canonical, 1) : $canonical;
    }

    /**
     * A parameter's name, in lowercase: sites read tracking parameters whatever their case.
     */
    private function name(string $parameter): string
    {
        return mb_strtolower(explode('=', $parameter, 2)[0]);
    }

    private function withUppercaseEscapes(string $value): string
    {
        return (string) preg_replace_callback('/%[0-9a-f]{2}/i', fn (array $matches): string => mb_strtoupper($matches[0]), $value);
    }
}
