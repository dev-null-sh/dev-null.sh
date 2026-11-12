<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLog;
use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Http\Resources\ShownStreams;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;
use App\Queries\LogCommitsQuery;
use App\Queries\SuggestedCommitsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class LogController
{
    /**
     * Guests land straight on the Trending log, with the home page's link preview.
     */
    public function index(ShowLogRequest $request, SuggestedCommitsQuery $suggested, LatestLogCommitsQuery $latest, LogCommitsQuery $commits, UpdateUserLog $action, PageMetadata $metadata): Response|SymfonyResponse
    {
        $response = $this->show($request, null, Log::Trending, $suggested, $latest, $commits, $action);

        return $response instanceof Response ? $response->withViewData(['preview' => $metadata->home()]) : $response;
    }

    /**
     * Guests read Trending and Latest, while Following takes them to sign in with GitHub.
     */
    public function show(ShowLogRequest $request, #[CurrentUser] ?User $user, Log $log, SuggestedCommitsQuery $suggested, LatestLogCommitsQuery $latest, LogCommitsQuery $commits, UpdateUserLog $action): Response|SymfonyResponse
    {
        if (! $user instanceof User) {
            if ($log === Log::Following) {
                if (app()->isLocal()) {
                    Inertia::flash('localSignIn', true);

                    return to_route('home');
                }

                return Inertia::location(route('login'));
            }
        } elseif ($user->log !== $log && ! $request->prefetch()) {
            $action->handle($user, $log);
        }

        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(function () use ($request, $log, $user, $commits): CursorPaginator {
                $through = $request->through();

                return ShownStreams::page($commits->paginate($log, $user, $through, $request->cursor($log), $request->throughAt($through)));
            }),
            'isRanked' => $log->isRanked(),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log, $latest): int {
                $seen = $request->since();

                return $seen instanceof Commit || $request->boolean('everything')
                    ? $latest->count($log, $user, $seen, $request->date('since_at'))
                    : 0;
            }),
            'removedCommitIds' => Inertia::optional(fn (): array => $latest->missing($log, $user, $request->pending())),
            'arrivedCommitIds' => Inertia::optional(function () use ($request, $latest, $log, $user): array {
                $through = $request->through();

                return $latest->arrived($log, $user, $request->arrived(), $through, $request->throughAt($through));
            }),
            'windowDigest' => Inertia::optional(function () use ($request, $latest, $log, $user): ?string {
                $through = $request->through();

                return $latest->window($log, $user, $through, $request->since(), $request->throughAt($through), $request->date('since_at'));
            }),
            'suggestions' => fn (): array => $user instanceof User && $log === Log::Following && ! $user->following()->exists()
                ? ShownStreams::commits($suggested->get($user))
                : [],
        ]);
    }
}
