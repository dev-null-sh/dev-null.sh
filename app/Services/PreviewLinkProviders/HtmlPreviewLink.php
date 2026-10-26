<?php

declare(strict_types=1);

namespace App\Services\PreviewLinkProviders;

use App\Contracts\PreviewLink;
use App\Dto\LinkPreviewMetadata;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class HtmlPreviewLink implements PreviewLink
{
    public const int MAX_BODY_BYTES = 1_048_576;

    public const string CONTENT_TYPE = 'text/html';

    private const int MAX_IMAGE_URL_LENGTH = 2048;

    private const int MAX_TITLE_LENGTH = 300;

    private const int MAX_DESCRIPTION_LENGTH = 500;

    private const int MAX_SITE_NAME_LENGTH = 100;

    public function metadata(Response $response, Url $finalUrl): LinkPreviewMetadata
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        if (! in_array($mediaType, [self::CONTENT_TYPE, 'application/xhtml+xml'], true)) {
            return new LinkPreviewMetadata;
        }

        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return $this->parse(Utils::copyToString($body, self::MAX_BODY_BYTES), $finalUrl);
    }

    private function parse(string $html, Url $page): LinkPreviewMetadata
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $meta = [];

        foreach ($document->querySelectorAll('meta[content]') as $element) {
            $key = mb_strtolower(mb_trim($element->getAttribute('property') ?? $element->getAttribute('name') ?? ''));
            $meta[$key] ??= (string) $element->getAttribute('content');
        }

        return new LinkPreviewMetadata(
            title: $this->text([$meta['og:title'] ?? '', $meta['twitter:title'] ?? '', $document->title], self::MAX_TITLE_LENGTH),
            description: $this->text([$meta['og:description'] ?? '', $meta['twitter:description'] ?? '', $meta['description'] ?? ''], self::MAX_DESCRIPTION_LENGTH),
            imageUrl: $this->imageUrl([$meta['og:image:secure_url'] ?? '', $meta['og:image'] ?? '', $meta['twitter:image'] ?? ''], $page),
            siteName: $this->text([$meta['og:site_name'] ?? ''], self::MAX_SITE_NAME_LENGTH),
        );
    }

    /**
     * @param  list<string>  $candidates
     */
    private function text(array $candidates, int $limit): ?string
    {
        foreach ($candidates as $candidate) {
            $text = Str::squish(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) <= $limit) {
                return $text;
            }

            return mb_rtrim(mb_substr($text, 0, $limit - 1)).'…';
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

            if ($image?->getScheme() !== 'https') {
                continue;
            }

            if (mb_strlen($image->toAsciiString()) > self::MAX_IMAGE_URL_LENGTH) {
                continue;
            }

            return $image->toAsciiString();
        }

        return null;
    }
}
