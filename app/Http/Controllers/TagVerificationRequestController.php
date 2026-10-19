<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListTagVerificationRequestsRequest;
use App\Queries\PendingTagVerificationsQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TagVerificationRequestController
{
    public function index(ListTagVerificationRequestsRequest $request, PendingTagVerificationsQuery $verifications): Response
    {
        return Inertia::render('moderation/verifications', [
            'verifications' => Inertia::scroll(fn (): CursorPaginator => $verifications->paginate()),
        ]);
    }
}
