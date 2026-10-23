<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Closure;

final readonly class LinkPreviewBudget
{
    private const int TIMEOUT_SECONDS = 5;

    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    /**
     * @param  (Closure(): int)|null  $clock
     */
    public function __construct(private ?Closure $clock = null)
    {
        //
    }

    public function start(): float
    {
        return $this->seconds() + self::TIMEOUT_SECONDS;
    }

    public function remaining(float $deadline): float
    {
        $milliseconds = floor(($deadline - $this->seconds()) * CarbonInterface::MILLISECONDS_PER_SECOND);

        return max(0, $milliseconds) / CarbonInterface::MILLISECONDS_PER_SECOND;
    }

    private function seconds(): float
    {
        $nanoseconds = $this->clock instanceof Closure ? ($this->clock)() : hrtime(true);

        return $nanoseconds / self::NANOSECONDS_PER_SECOND;
    }
}
