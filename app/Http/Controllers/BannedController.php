<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ban;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BannedController
{
    /**
     * Where a banned user lands from everywhere else, told why, with deleting their account as the way out.
     */
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        $ban = $user->ban;

        if (! $ban instanceof Ban) {
            return to_route('log.show', $user->log);
        }

        return Inertia::render('banned/show', [
            'reason' => $ban->reason->label(),
            'bannedAt' => $ban->created_at,
        ]);
    }
}
