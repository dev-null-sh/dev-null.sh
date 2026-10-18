<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Queries\LogQuery;
use App\Queries\LogQueries\FollowingLogQuery;
use App\Queries\LogQueries\LatestLogQuery;
use App\Queries\LogQueries\TrendingLogQuery;

enum Log: string
{
    case Following = 'following';
    case Latest = 'latest';
    case Trending = 'trending';

    /**
     * A ranked log is not newest first, so it does not offer the newest commits: the Latest log does.
     */
    public function isRanked(): bool
    {
        return $this === self::Trending;
    }

    public function query(): LogQuery
    {
        return resolve(match ($this) {
            self::Following => FollowingLogQuery::class,
            self::Latest => LatestLogQuery::class,
            self::Trending => TrendingLogQuery::class,
        });
    }
}
