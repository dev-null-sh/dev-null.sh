<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListChangelogRequest;
use App\Queries\ChangelogQuery;
use App\Services\PageMetadata;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ChangelogController
{
    public function index(ListChangelogRequest $request, ChangelogQuery $changelog): JsonResponse|Response
    {
        if (! $request->expectsJson()) {
            $metadata = resolve(PageMetadata::class);

            return Inertia::render('changelog/show')->withViewData(['preview' => $metadata->page(
                'Changelog - /dev/null',
                'What was merged into /dev/null, day by day: every pull request that shipped to production, newest first, with who made it.',
                route('changelog.index'),
            )]);
        }

        $days = $changelog->get($request->timeZone(), $request->before());

        return response()->json([
            'pullRequests' => $days['pullRequests'] ?? null,
            'olderBefore' => $days['olderBefore'] ?? null,
            'commitsUrl' => 'https://github.com/'.config()->string('services.github.repository').'/commits/'.ChangelogQuery::Branch,
        ]);
    }
}
