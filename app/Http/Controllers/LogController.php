<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLog;
use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;
use App\Queries\SuggestedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LogController
{
    public function show(ShowLogRequest $request, #[CurrentUser] User $user, Log $log, SuggestedCommitsQuery $suggested, LatestLogCommitsQuery $latest, UpdateUserLog $action): Response
    {
        if ($user->log !== $log) {
            $action->handle($user, $log);
        }

        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder($user)
                ->cursorPaginate(20)),
            'isRanked' => $log->isRanked(),
            'newestCommitId' => fn (): ?string => $log->isRanked() ? $latest->newestId($log, $user) : null,
            'latestCommits' => Inertia::merge(fn (): Collection => $latest->get($log, $user, $request->since(), $request->committed()))
                ->prepend()
                ->matchOn('id'),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log, $latest): int {
                $seen = $request->since();

                return $seen instanceof Commit ? $latest->count($log, $user, $seen) : 0;
            }),
            'suggestions' => fn (): Collection => $log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection(),
        ]);
    }
}
