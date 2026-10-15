<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModerationController
{
    public function show(#[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/show', [
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->pending()
                ->with(['user', 'parent.user', 'linkPreview'])
                ->withStarsFor($user)
                ->oldest()
                ->oldest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
