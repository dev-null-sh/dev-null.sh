<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LogController
{
    public function show(Request $request, #[CurrentUser] User $user, Log $log, SuggestedCommitsQuery $suggested): Response
    {
        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder($user)
                ->cursorPaginate(20)),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log): int {
                $since = $request->string('since')->value();
                $seen = Str::isUuid($since) ? Commit::query()->withTrashed()->find($since) : null;

                return $seen instanceof Commit
                    ? $log->query()->builder($user)->where('created_at', '>', $seen->created_at)->count()
                    : 0;
            }),
            'suggestions' => fn (): Collection => $log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection(),
        ]);
    }
}
