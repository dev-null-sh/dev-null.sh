<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommitReport;
use App\Models\User;
use App\Queries\ReportedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ReportedCommitController
{
    public function index(#[CurrentUser] User $user, ReportedCommitsQuery $reports): Response
    {
        return Inertia::render('moderation/reports', [
            'reports' => Inertia::scroll(fn (): CursorPaginator => $reports->paginate($user)),
            'reasons' => CommitReport::REASONS,
        ]);
    }
}
