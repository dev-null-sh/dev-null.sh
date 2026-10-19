<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOperationsRequest;
use App\Models\Commit;
use App\Queries\ConnectivityQuery;
use App\Queries\FailedJobsQuery;
use App\Queries\FathomTrafficQuery;
use App\Queries\GitHubRateLimitQuery;
use App\Queries\LinkPreviewHealthQuery;
use App\Queries\QueueBacklogQuery;
use App\Queries\RecentVolumeQuery;
use App\Services\Features;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OperationsController
{
    /**
     * The GitHub rate limit and Fathom traffic are deferred, so a slow third party never holds up the page.
     */
    public function show(
        ShowOperationsRequest $request,
        QueueBacklogQuery $queues,
        FailedJobsQuery $failedJobs,
        ConnectivityQuery $connectivity,
        LinkPreviewHealthQuery $linkPreviews,
        RecentVolumeQuery $volume,
        GitHubRateLimitQuery $githubRateLimit,
        FathomTrafficQuery $traffic,
        Features $features,
    ): Response {
        return Inertia::render('operations/show', [
            'queues' => $queues->get(...),
            'failedJobs' => $failedJobs->get(...),
            'connectivity' => $connectivity->get(...),
            'linkPreviews' => $linkPreviews->get(...),
            'pendingModerationCount' => fn (): int => Commit::query()->pending()->count(),
            'volume' => $volume->get(...),
            'features' => $features->all(...),
            ...$githubRateLimit->isConfigured() ? [
                'githubRateLimit' => Inertia::defer($githubRateLimit->get(...)),
            ] : [],
            ...$traffic->isConfigured() ? [
                'trafficRange' => ['from' => $request->trafficFrom()->toDateString(), 'to' => $request->trafficTo()->toDateString()],
                'traffic' => Inertia::defer(fn (): ?array => $traffic->get($request->trafficFrom(), $request->trafficTo())),
            ] : [],
        ]);
    }
}
