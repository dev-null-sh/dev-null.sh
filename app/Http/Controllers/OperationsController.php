<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOperationsRequest;
use App\Models\Commit;
use App\Queries\ConnectivityQuery;
use App\Queries\FailedJobsQuery;
use App\Queries\GitHubRateLimitQuery;
use App\Queries\LinkPreviewHealthQuery;
use App\Queries\QueueBacklogQuery;
use App\Queries\RecentVolumeQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OperationsController
{
    /**
     * The GitHub rate limit is deferred, so a slow GitHub never holds up the page.
     */
    public function show(
        ShowOperationsRequest $request,
        QueueBacklogQuery $queues,
        FailedJobsQuery $failedJobs,
        ConnectivityQuery $connectivity,
        LinkPreviewHealthQuery $linkPreviews,
        RecentVolumeQuery $volume,
        GitHubRateLimitQuery $githubRateLimit,
    ): Response {
        return Inertia::render('operations/show', [
            'queues' => $queues->get(...),
            'failedJobs' => $failedJobs->get(...),
            'connectivity' => $connectivity->get(...),
            'linkPreviews' => $linkPreviews->get(...),
            'pendingModerationCount' => fn (): int => Commit::query()->pending()->count(),
            'volume' => $volume->get(...),
            ...$githubRateLimit->isConfigured() ? [
                'githubRateLimit' => Inertia::defer($githubRateLimit->get(...)),
            ] : [],
        ]);
    }
}
