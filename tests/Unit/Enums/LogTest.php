<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Queries\LogQueries\EveryoneLogQuery;
use App\Queries\LogQueries\FollowingLogQuery;

it('resolves the query of each log', function (Log $log, string $query): void {
    expect($log->query())->toBeInstanceOf($query);
})->with([
    [Log::Following, FollowingLogQuery::class],
    [Log::Everyone, EveryoneLogQuery::class],
]);
