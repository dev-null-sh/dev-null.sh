<?php

declare(strict_types=1);

namespace App\Actions;

use Dom\HTMLDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

/**
 * Pulls public homepage meta for a verified domain (description and og:image for the branch page).
 */
final readonly class FetchDomainSiteMeta
{
    public const int MAX_DESCRIPTION_LENGTH = 500;

    public const int MAX_IMAGE_URL_LENGTH = 2048;

    /**
     * @return array{description: string|null, image_url: string|null}
     */
    public function forDomain(string $domain): array
    {
        $html = $this->homepage($domain);

        if ($html === null) {
            return [
                'description' => null,
                'image_url' => null,
            ];
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $meta = [];

        foreach ($document->querySelectorAll('meta[content]') as $element) {
            $key = mb_strtolower(mb_trim($element->getAttribute('property') ?? $element->getAttribute('name') ?? ''));
            $meta[$key] ??= (string) $element->getAttribute('content');
        }

        $page = Url::parse('https://'.$domain.'/');

        return [
            'description' => $this->text([
                $meta['og:description'] ?? '',
                $meta['twitter:description'] ?? '',
                $meta['description'] ?? '',
            ]),
            'image_url' => $this->imageUrl([
                $meta['og:image:secure_url'] ?? '',
                $meta['og:image'] ?? '',
                $meta['twitter:image'] ?? '',
            ], $page),
        ];
    }

    public function description(string $domain): ?string
    {
        return $this->forDomain($domain)['description'];
    }

    private function homepage(string $domain): ?string
    {
        try {
            $response = Http::withUserAgent(sprintf(
                'Mozilla/5.0 (compatible; devnull-branch/1.0; +%s)',
                config()->string('app.url'),
            ))
                ->accept('text/html')
                ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
                ->connectTimeout(3)
                ->timeout(5)
                ->get('https://'.$domain.'/');
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        if ($mediaType !== '' && ! in_array($mediaType, ['text/html', 'application/xhtml+xml'], true)) {
            return null;
        }

        return mb_substr($response->body(), 0, 1_000_000);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function text(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $text = Str::squish(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($text !== '') {
                return mb_strlen($text) > self::MAX_DESCRIPTION_LENGTH
                    ? mb_rtrim(mb_substr($text, 0, self::MAX_DESCRIPTION_LENGTH - 1)).'…'
                    : $text;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function imageUrl(array $candidates, Url $page): ?string
    {
        foreach ($candidates as $candidate) {
            $image = mb_trim($candidate) === '' ? null : Url::parse($candidate, $page);

            if ($image?->getScheme() === 'https' && mb_strlen($image->toAsciiString()) <= self::MAX_IMAGE_URL_LENGTH) {
                return $image->toAsciiString();
            }
        }

        return null;
    }
}
