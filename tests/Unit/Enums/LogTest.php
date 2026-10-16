<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Queries\LogQueries\FollowingLogQuery;
use App\Queries\LogQueries\LatestLogQuery;
use App\Queries\LogQueries\TrendingLogQuery;

it('resolves the query of each log', function (Log $log, string $query): void {
    expect($log->query())->toBeInstanceOf($query);
})->with([
    [Log::Following, FollowingLogQuery::class],
    [Log::Latest, LatestLogQuery::class],
    [Log::Trending, TrendingLogQuery::class],
]);

it('only ranks the trending log', function (Log $log, bool $isRanked): void {
    expect($log->isRanked())->toBe($isRanked);
})->with([
    [Log::Following, false],
    [Log::Latest, false],
    [Log::Trending, true],
]);
