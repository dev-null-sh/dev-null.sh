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
     * A ranked log is not newest first, so the newest commits are offered on top of it instead.
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
