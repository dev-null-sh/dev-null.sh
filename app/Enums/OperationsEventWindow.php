<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum OperationsEventWindow: string
{
    case Hour = '1h';
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    public function bucketSeconds(): int
    {
        return match ($this) {
            self::Hour => 300,
            self::Day => 3600,
            self::Week => 21600,
            self::Month => 86400,
        };
    }

    public function buckets(): int
    {
        return match ($this) {
            self::Hour => 12,
            self::Day => 24,
            self::Week => 28,
            self::Month => 30,
        };
    }

    /**
     * The start of the first bucket. Buckets are aligned to their size, as UTC days are, and the last one is the one
     * now falls in, so the window reaches back a little further than its name until that bucket is over.
     */
    public function start(CarbonImmutable $now): CarbonImmutable
    {
        $size = $this->bucketSeconds();

        return CarbonImmutable::createFromTimestamp((intdiv($now->getTimestamp(), $size) - $this->buckets() + 1) * $size);
    }
}
