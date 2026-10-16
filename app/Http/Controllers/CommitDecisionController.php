<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitDecisionsRequest;
use App\Queries\CommitDecisionsQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitDecisionController
{
    public function index(ListCommitDecisionsRequest $request, CommitDecisionsQuery $decisions): Response
    {
        return Inertia::render('moderation/decisions', [
            'decisions' => Inertia::scroll(fn (): CursorPaginator => $decisions->paginate()),
        ]);
    }
}
