<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowProductMetricsRequest;
use App\Queries\ActiveUserCountsQuery;
use App\Queries\ActiveUsersQuery;
use App\Queries\CommitResponsesQuery;
use App\Queries\NewcomerFollowsQuery;
use App\Queries\ProductFunnelQuery;
use App\Queries\RetentionByDayQuery;
use App\Queries\RetentionCohortsQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ProductMetricsController
{
    /**
     * The funnel is deferred, as it waits on Fathom for the visitors and the conversion.
     */
    public function show(
        ShowProductMetricsRequest $request,
        ProductFunnelQuery $funnel,
        ActiveUsersQuery $activeUsers,
        CommitResponsesQuery $responses,
        NewcomerFollowsQuery $newcomerFollows,
        ActiveUserCountsQuery $activeUserCounts,
        RetentionByDayQuery $retentionByDay,
        RetentionCohortsQuery $retentionCohorts,
    ): Response {
        $from = $request->from();
        $to = $request->to();

        return Inertia::render('operations/metrics', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'all_time' => $request->isAllTime()],
            'funnel' => Inertia::defer(fn (): array => $funnel->get($from, $to, $request->isAllTime())),
            'activeUsers' => $activeUsers->get(...),
            'responses' => fn (): array => $responses->get($from, $to),
            'newcomerFollows' => fn (): array => $newcomerFollows->get($from, $to),
            'activeUserCounts' => fn (): array => $activeUserCounts->get($from, $to),
            'retentionByDay' => fn (): array => [
                'day_1' => $retentionByDay->get($from, $to, 1),
                'day_7' => $retentionByDay->get($from, $to, 7),
            ],
            'retentionCohorts' => fn (): array => $retentionCohorts->get($from, $to),
            'retentionRecordedSince' => RetentionByDayQuery::RECORDED_SINCE,
        ]);
    }
}
