<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowForecastRequest;
use App\Queries\FathomTrafficQuery;
use App\Queries\ForecastsQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ForecastController
{
    public function show(ShowForecastRequest $request, ForecastsQuery $forecasts, FathomTrafficQuery $traffic): Response
    {
        return Inertia::render('operations/forecasts', [
            'forecasts' => $forecasts->get(...),
            'milestones' => $forecasts->milestones(...),
            'month' => $forecasts->month(...),
            'hasTraffic' => $traffic->isConfigured(),
        ]);
    }
}
