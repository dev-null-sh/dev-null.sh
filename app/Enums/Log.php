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

    public function query(): LogQuery
    {
        return resolve(match ($this) {
            self::Following => FollowingLogQuery::class,
            self::Everyone => EveryoneLogQuery::class,
        });
    }
}
