<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOperationsRequest;
use App\Queries\DailySeriesQuery;
use App\Queries\ForecastsQuery;
use App\Queries\OperationsEventsQuery;
use App\Queries\OperationsSignalsQuery;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OperationsController
{
    public function show(
        ShowOperationsRequest $request,
        OperationsSignalsQuery $signals,
        DailySeriesQuery $daily,
        ForecastsQuery $forecasts,
        OperationsEventsQuery $events,
    ): Response {
        return Inertia::render('operations/show', [
            'signals' => $signals->get(...),
            'daily' => fn (): array => $daily->get(CarbonImmutable::today()->subDays(27), CarbonImmutable::today()),
            'forecasts' => $forecasts->get(...),
            'recentEvents' => fn (): array => $events->get(CarbonImmutable::now()->subDay(), limit: 10),
        ]);
    }
}
