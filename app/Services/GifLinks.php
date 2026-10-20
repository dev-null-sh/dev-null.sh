<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class GifLinks
{
    public const string CONTENT_TYPE = 'image/gif';

    public const int HEADER_BYTES = 32;

    private const int MIN_HEADER_BYTES = 13;

    private const int MAX_URL_LENGTH = 2048;

    private const int SIGNATURE_BYTES = 6;

    private const int WIDTH_OFFSET = 6;

    private const int HEIGHT_OFFSET = 8;

    private const int DIMENSION_BYTES = 2;

    private const array SIGNATURES = ['GIF87a', 'GIF89a'];

    private const string ZERO_DIMENSION = "\0\0";

    private const string BINARY_ENCODING = '8bit';

    public function isGifUrl(?Url $url): bool
    {
        if ($url?->getScheme() !== 'https') {
            return false;
        }

        return str_ends_with(mb_strtolower($url->getPath()), '.gif');
    }

    public function getVerifiedUrl(Url $url, string $contentType, string $header): ?string
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($contentType, ';')));

        if ($mediaType !== self::CONTENT_TYPE) {
            return null;
        }

        $sourceUrl = $url->toAsciiString();

        if (mb_strlen($sourceUrl) > self::MAX_URL_LENGTH) {
            return null;
        }

        if (! $this->isValidHeader($header)) {
            return null;
        }

        return $sourceUrl;
    }

    private function isValidHeader(string $header): bool
    {
        if (mb_strlen($header, self::BINARY_ENCODING) < self::MIN_HEADER_BYTES) {
            return false;
        }

        return $this->hasValidSignature($header) && $this->hasValidDimensions($header);
    }

    private function hasValidSignature(string $header): bool
    {
        $signature = mb_substr($header, 0, self::SIGNATURE_BYTES, self::BINARY_ENCODING);

        return in_array($signature, self::SIGNATURES, true);
    }

    private function hasValidDimensions(string $header): bool
    {
        $width = mb_substr($header, self::WIDTH_OFFSET, self::DIMENSION_BYTES, self::BINARY_ENCODING);
        $height = mb_substr($header, self::HEIGHT_OFFSET, self::DIMENSION_BYTES, self::BINARY_ENCODING);

        return $width !== self::ZERO_DIMENSION && $height !== self::ZERO_DIMENSION;
    }
}
