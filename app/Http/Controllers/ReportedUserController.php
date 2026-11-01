<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListReportedUsersRequest;
use App\Models\CommitReport;
use App\Queries\ReportedUsersQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ReportedUserController
{
    public function index(ListReportedUsersRequest $request, ReportedUsersQuery $reports): Response
    {
        return Inertia::render('moderation/profile-reports', [
            'reports' => Inertia::scroll(fn (): CursorPaginator => $reports->paginate()),
            'reasons' => CommitReport::REASONS,
        ]);
    }
}
