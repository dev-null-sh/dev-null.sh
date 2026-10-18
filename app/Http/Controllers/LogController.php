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
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class LogController
{
    /**
     * Guests land straight on the Trending log, with the home page's link preview.
     */
    public function index(ShowLogRequest $request, SuggestedCommitsQuery $suggested, LatestLogCommitsQuery $latest, UpdateUserLog $action, PageMetadata $metadata): Response|SymfonyResponse
    {
        $response = $this->show($request, null, Log::Trending, $suggested, $latest, $action);

        return $response instanceof Response ? $response->withViewData(['preview' => $metadata->home()]) : $response;
    }

    /**
     * Guests read Trending and Latest, while Following takes them to sign in with GitHub.
     */
    public function show(ShowLogRequest $request, #[CurrentUser] ?User $user, Log $log, SuggestedCommitsQuery $suggested, LatestLogCommitsQuery $latest, UpdateUserLog $action): Response|SymfonyResponse
    {
        if (! $user instanceof User) {
            if ($log === Log::Following) {
                return Inertia::location(route('login'));
            }
        } elseif ($user->log !== $log) {
            $action->handle($user, $log);
        }

        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder($user)
                ->cursorPaginate(20)),
            'isRanked' => $log->isRanked(),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log, $latest): int {
                $seen = $request->since();

                return $seen instanceof Commit ? $latest->count($log, $user, $seen) : 0;
            }),
            'suggestions' => fn (): Collection => $user instanceof User && $log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection(),
        ]);
    }
}
