<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AutoModeratedCommitController
{
    /**
     * Automatic decisions take commits out of the queue, so they are listed here to be checked and undone.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/automated', [
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->whereHas('evaluation', fn (Builder $query): Builder => $query
                    ->whereIn('decision', [EvaluationDecision::Approved, EvaluationDecision::Rejected]))
                ->withSavedState($user)
                ->with(['user', 'parent.user', 'linkPreview', 'images', 'evaluation'])
                ->withStarsFor($user)
                ->latest('updated_at')
                ->latest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
