<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DismissCommitReport;
use App\Actions\ReportCommit;
use App\Http\Requests\CreateCommitReportRequest;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitReportController
{
    public function store(CreateCommitReportRequest $request, #[CurrentUser] User $user, Commit $commit, ReportCommit $action): RedirectResponse
    {
        $action->handle($user, $commit, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report received. Moderators will review it privately.')]);

        return back();
    }

    public function destroy(CommitReport $report, DismissCommitReport $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
