<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListBranchVerificationRequestsRequest;
use App\Queries\PendingBranchVerificationsQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BranchVerificationRequestController
{
    public function index(ListBranchVerificationRequestsRequest $request, PendingBranchVerificationsQuery $verifications): Response
    {
        return Inertia::render('moderation/verifications', [
            'verifications' => Inertia::scroll(fn (): CursorPaginator => $verifications->paginate()),
        ]);
    }
}
