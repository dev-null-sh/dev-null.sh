<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowModerationRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModerationController
{
    public function show(ShowModerationRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/show', [
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->pending()
                ->withSavedState($user)
                ->with(['user', 'parent.user', 'linkPreview', 'images'])
                ->withStarsFor($user)
                ->oldest()
                ->oldest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
