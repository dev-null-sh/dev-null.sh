<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;

final readonly class TheAlgorithm
{
    /**
     * A commit needs twice the engagement to rank with one committed this many seconds later.
     */
    private const int DOUBLING_PERIOD = 3 * 60 * 60;

    /**
     * Replying or quoting takes more than starring, so each developer replying or quoting in a thread counts for this many
     * developers who only starred it.
     */
    private const int REPLIER_WEIGHT = 3;

    /**
     * The score grows with time rather than decaying with age, so it only changes when engagement does and can be
     * stored and indexed. Engagement counts on a log scale: the first stars lift a commit more than later ones do. It is
     * rounded so that it reads back from the database exactly as it was written, which the log's cursor relies on.
     */
    public function score(CarbonInterface $createdAt, int $stargazers, int $repliers): float
    {
        return round(log(1 + $stargazers + self::REPLIER_WEIGHT * $repliers, 2) + $createdAt->getTimestamp() / self::DOUBLING_PERIOD, 6);
    }
}
