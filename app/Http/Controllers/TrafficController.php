<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\AnalyticsProvider;
use App\Http\Requests\ShowTrafficRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TrafficController
{
    /**
     * The traffic is deferred, so a slow third party never holds up the page.
     */
    public function show(ShowTrafficRequest $request, AnalyticsProvider $analytics): Response
    {
        return Inertia::render('operations/traffic', [
            'trafficRange' => ['from' => $request->from()->toDateString(), 'to' => $request->to()->toDateString()],
            'traffic' => Inertia::defer(fn (): ?array => $analytics->traffic($request->from(), $request->to())),
        ]);
    }
}
