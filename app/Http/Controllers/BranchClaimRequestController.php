<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListBranchClaimRequestsRequest;
use App\Queries\PendingBranchClaimsQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BranchClaimRequestController
{
    public function index(ListBranchClaimRequestsRequest $request, PendingBranchClaimsQuery $claims): Response
    {
        return Inertia::render('moderation/claims', [
            'claims' => Inertia::scroll(fn (): CursorPaginator => $claims->paginate()),
        ]);
    }
}
