<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateExploreReport;
use App\Http\Requests\ListExploreReportRequest;
use App\Http\Requests\UpdateExploreReportRequest;
use App\Models\ExploreTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExploreReportController
{
    public function index(ListExploreReportRequest $request): Response
    {
        return Inertia::render('moderation/explore', [
            'reports' => DB::table('explore_reports')->join('explore_topics', 'explore_topics.id', '=', 'topic_id')
                ->whereNull('resolved_at')->oldest('explore_reports.created_at')->get(['topic_id', 'title', 'kind', 'reason', 'explore_reports.created_at']),
            'suppressed' => ExploreTopic::query()->whereNotNull('suppressed_at')->orderBy('title')->get(),
            'reasons' => ExploreTopic::REASONS,
        ]);
    }

    public function update(UpdateExploreReportRequest $request, ExploreTopic $topic, UpdateExploreReport $action): RedirectResponse
    {
        $action->handle($topic, $request->has('suppressed') ? $request->boolean('suppressed') : null);

        return back();
    }
}
