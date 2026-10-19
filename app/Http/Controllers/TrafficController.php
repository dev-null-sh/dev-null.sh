<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowTrafficRequest;
use App\Queries\FathomTrafficQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TrafficController
{
    /**
     * The Fathom traffic is deferred, so a slow third party never holds up the page.
     */
    public function show(ShowTrafficRequest $request, FathomTrafficQuery $traffic): Response
    {
        return Inertia::render('operations/traffic', [
            'trafficRange' => ['from' => $request->from()->toDateString(), 'to' => $request->to()->toDateString()],
            'traffic' => Inertia::defer(fn (): ?array => $traffic->get($request->from(), $request->to())),
            'hasTraffic' => true,
        ]);
    }
}
