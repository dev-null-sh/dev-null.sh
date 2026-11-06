<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitChangeRequest;
use App\Models\User;
use App\Queries\CommitChangesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitChangeController
{
    /**
     * What changed about the commits a page shows since it read them, so a page that may have missed live updates
     * reads again only what did.
     */
    public function index(ListCommitChangeRequest $request, #[CurrentUser] ?User $user, CommitChangesQuery $changes): JsonResponse
    {
        return response()->json(['changes' => $changes->get($user, $request->ids(), $request->since())])
            ->header('Cache-Control', 'no-store, private');
    }
}
