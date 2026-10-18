<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\StatisticsQuery;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class StatisticsController
{
    public function show(StatisticsQuery $statistics, PageMetadata $metadata): Response
    {
        return Inertia::render('statistics/show', [
            'statistics' => $statistics->get(...),
        ])->withViewData(['preview' => $metadata->page(
            'Statistics - /dev/null',
            'The /dev/null community in numbers: developers, public commits, and daily activity over the last 30 days.',
            route('statistics.show'),
        )]);
    }
}
