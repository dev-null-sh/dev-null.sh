<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class CommitReportPolicy
{
    public function create(User $user, Commit $commit): Response
    {
        if ($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $commit->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $commit->user_id !== $user->id
            ? Response::allow()
            : Response::deny('You cannot report your own commit.');
    }

    public function dismiss(User $user, CommitReport $report): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $report->commit?->status === CommitStatus::Approved
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
