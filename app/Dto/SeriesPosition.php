<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class SeriesPosition
{
    public function __construct(
        public string $seriesId,
        public int $position,
        public int $size,
    ) {
        //
    }
}
