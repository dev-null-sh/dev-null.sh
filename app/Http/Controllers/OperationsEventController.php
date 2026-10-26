<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOperationsEventsRequest;
use App\Queries\FathomTrafficQuery;
use App\Queries\OperationsEventsQuery;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OperationsEventController
{
    public function index(ListOperationsEventsRequest $request, OperationsEventsQuery $events, FathomTrafficQuery $traffic): Response
    {
        $since = $request->window()->start(CarbonImmutable::now());

        return Inertia::render('operations/events', [
            'events' => fn (): array => $events->get($since, $request->source(), $request->level(), $request->search()),
            'filters' => [
                'window' => $request->window()->value,
                'source' => $request->source()?->value,
                'level' => $request->level()?->value,
                'search' => $request->search(),
            ],
            'counts' => fn (): array => $events->counts($since, $request->level(), $request->search()),
            'histogram' => fn (): array => $events->histogram($request->window(), $request->source(), $request->level(), $request->search()),
            'hasTraffic' => $traffic->isConfigured(),
        ]);
    }
}
