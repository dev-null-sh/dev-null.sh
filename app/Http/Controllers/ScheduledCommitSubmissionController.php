<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Http\Requests\CreateScheduledCommitSubmissionRequest;
use App\Models\ScheduledCommit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class ScheduledCommitSubmissionController
{
    public function store(CreateScheduledCommitSubmissionRequest $request, ScheduledCommit $scheduledCommit, UpdateScheduledCommitSubmittedAt $action): RedirectResponse
    {
        $submitted = $action->handle($scheduledCommit->id, early: true);

        if ($submitted) {
            Inertia::flash('submitted', $scheduledCommit->id);
        }

        Inertia::flash('toast', ['type' => $submitted ? 'success' : 'info', 'message' => $submitted
            ? 'Commit submitted. It goes through the usual review process.'
            : 'This commit was already submitted or canceled. Refresh to see its status.']);

        return back();
    }
}
