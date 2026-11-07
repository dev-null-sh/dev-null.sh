<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListReportedUsersRequest;
use App\Models\CommitReport;
use App\Queries\ReportedUsersQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ReportedUserController
{
    /**
     * Reports on messages in remotes and on people, which moderators act on from the profile.
     */
    public function index(ListReportedUsersRequest $request, ReportedUsersQuery $query): Response
    {
        return Inertia::render('moderation/people-reports', [
            'messageReports' => $query->messages(...),
            'userReports' => $query->users(...),
            'reasons' => CommitReport::REASONS,
        ]);
    }
}
