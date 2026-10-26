<?php

declare(strict_types=1);

namespace App\Services\LinkPreviews;

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;

final class LimitedResponseBody extends Stream
{
    private const string BINARY_ENCODING = '8bit';

    public function __construct(private readonly int $maxBytes)
    {
        parent::__construct(Utils::tryFopen('php://temp', 'w+'));
    }

    public function write(string $string): int
    {
        $remaining = max(0, $this->maxBytes - $this->tell());

        return parent::write(mb_substr($string, 0, $remaining, self::BINARY_ENCODING));
    }
}
