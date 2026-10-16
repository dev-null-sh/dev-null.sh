<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Queries\LogQuery;
use App\Queries\LogQueries\EveryoneLogQuery;
use App\Queries\LogQueries\FollowingLogQuery;

enum Log: string
{
    case Following = 'following';
    case Everyone = 'everyone';

    /**
     * A ranked log is not newest first, so the newest commits are offered on top of it instead.
     */
    public function isRanked(): bool
    {
        return $this === self::Everyone;
    }

    public function query(): LogQuery
    {
        return resolve(match ($this) {
            self::Following => FollowingLogQuery::class,
            self::Everyone => EveryoneLogQuery::class,
        });
    }
}
