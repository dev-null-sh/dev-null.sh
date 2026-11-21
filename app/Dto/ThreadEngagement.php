<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * The other developers taking part in a thread, each counted once per thing they did. A developer who replies, quotes
 * and stars counts in all three. The author never counts.
 */
final readonly class ThreadEngagement
{
    public function __construct(
        public int $stargazers,
        public int $quoters,
        public int $repliers,
        public int $repliersAnsweredByAuthor,
    ) {
        //
    }

    public static function none(): self
    {
        return new self(stargazers: 0, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0);
    }
}
