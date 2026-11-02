<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitReport;
use App\Actions\UpdateCommitReportDismissedAt;
use App\Http\Requests\CreateCommitReportRequest;
use App\Http\Requests\DeleteCommitReportRequest;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitReportController
{
    public function store(CreateCommitReportRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitReport $action): RedirectResponse
    {
        $action->handle($user, $commit, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null, $request->images(), $request->imageDescriptions());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Report received. Moderators will review it privately.']);

        return back();
    }

    public function destroy(DeleteCommitReportRequest $request, CommitReport $report, UpdateCommitReportDismissedAt $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
