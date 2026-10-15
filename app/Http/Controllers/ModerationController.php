<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModerationController
{
    public function show(#[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/show', [
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->pending()
                ->exceptLaterPendingSeriesParts()
                ->withSavedState($user)
                ->with([
                    'user',
                    'parent.user',
                    'linkPreview',
                    'seriesParts' => fn (HasMany $query): HasMany => $query
                        ->where('status', CommitStatus::Pending)
                        ->with(['user', 'linkPreview'])
                        ->oldest('series_position'),
                ])
                ->withStarsFor($user)
                ->oldest()
                ->oldest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
