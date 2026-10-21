<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListModeratedUsersRequest;
use App\Queries\ModeratedUsersQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModeratedUserController
{
    /**
     * Everyone in timeout and every ban, so moderators can lift them.
     */
    public function index(ListModeratedUsersRequest $request, ModeratedUsersQuery $query): Response
    {
        return Inertia::render('moderation/people', [
            'timeouts' => $query->timeouts(...),
            'bans' => $query->bans(...),
        ]);
    }
}
