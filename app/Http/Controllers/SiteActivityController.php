<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowSiteActivityRequest;
use App\Queries\ActivityBreakdownsQuery;
use App\Queries\OverallVolumeQuery;
use App\Queries\SiteActivityQuery;
use App\Queries\TodayVolumeQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SiteActivityController
{
    public function show(
        ShowSiteActivityRequest $request,
        TodayVolumeQuery $today,
        OverallVolumeQuery $overall,
        SiteActivityQuery $activity,
        ActivityBreakdownsQuery $breakdowns,
    ): Response {
        return Inertia::render('operations/activity', [
            'today' => $today->get(...),
            'overall' => $overall->get(...),
            'activity' => fn (): array => $activity->get($request->activityMetric(), $request->activityScale()),
            'breakdowns' => fn (): array => $breakdowns->get($request->activityScale()),
        ]);
    }
}
