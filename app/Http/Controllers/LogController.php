<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LogController
{
    public function show(ShowLogRequest $request, #[CurrentUser] User $user, Log $log, SuggestedCommitsQuery $suggested): Response
    {
        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder($user)
                ->cursorPaginate(20)),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log): int {
                $seen = $request->since();

                return $seen instanceof Commit
                    ? $log->query()->builder($user)
                        ->where(fn (Builder $query): Builder => $query
                            ->where('created_at', '>', $seen->created_at)
                            ->orWhere(fn (Builder $query): Builder => $query
                                ->where('created_at', $seen->created_at)
                                ->where('id', '>', $seen->id)))
                        ->count()
                    : 0;
            }),
            'suggestions' => fn (): Collection => $log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection(),
        ]);
    }
}
