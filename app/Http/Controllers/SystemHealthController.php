<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowSystemHealthRequest;
use App\Queries\ConnectivityQuery;
use App\Queries\FailedJobsQuery;
use App\Queries\FailedJobTrendQuery;
use App\Queries\GitHubRateLimitQuery;
use App\Queries\LinkPreviewHealthQuery;
use App\Queries\ModerationBacklogQuery;
use App\Queries\QueueBacklogQuery;
use App\Queries\RuntimeQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SystemHealthController
{
    /**
     * The GitHub rate limit is deferred, so a slow third party never holds up the page.
     */
    public function show(
        ShowSystemHealthRequest $request,
        QueueBacklogQuery $queues,
        FailedJobsQuery $failedJobs,
        FailedJobTrendQuery $failureTrend,
        ConnectivityQuery $connectivity,
        LinkPreviewHealthQuery $linkPreviews,
        ModerationBacklogQuery $moderation,
        RuntimeQuery $runtime,
        GitHubRateLimitQuery $githubRateLimit,
    ): Response {
        return Inertia::render('operations/health', [
            'queues' => $queues->get(...),
            'failedJobs' => $failedJobs->get(...),
            'failureTrend' => $failureTrend->get(...),
            'connectivity' => $connectivity->get(...),
            'linkPreviews' => $linkPreviews->get(...),
            'moderation' => $moderation->get(...),
            'runtime' => $runtime->get(...),
            'githubRateLimit' => Inertia::defer($githubRateLimit->get(...)),
        ]);
    }
}
