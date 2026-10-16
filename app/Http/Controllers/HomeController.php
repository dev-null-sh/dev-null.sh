<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;
use App\Services\PageMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class HomeController
{
    /**
     * There is no landing page: guests read the log everyone reads, Trending unless they asked for Latest, and sign
     * in from there.
     */
    public function show(ShowLogRequest $request, LatestLogCommitsQuery $latest, PageMetadata $metadata): RedirectResponse|Response
    {
        if (app()->isLocal() && config('app.auto_login')) {
            return $this->signInLocalDeveloper($request);
        }

        $log = $request->enum('log', Log::class) === Log::Latest ? Log::Latest : Log::Trending;

        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder(null)
                ->cursorPaginate(20)
                ->appends($log === Log::Latest ? ['log' => $log->value] : [])),
            'isRanked' => $log->isRanked(),
            'newestCommitId' => fn (): ?string => $log->isRanked() ? $latest->newestId($log, null) : null,
            'latestCommits' => Inertia::merge(fn (): Collection => $latest->get($log, null, $request->since(), null))
                ->prepend()
                ->matchOn('id'),
            'newCommitsCount' => Inertia::optional(function () use ($request, $log, $latest): int {
                $seen = $request->since();

                return $seen instanceof Commit ? $latest->count($log, null, $seen) : 0;
            }),
            'suggestions' => new Collection(),
        ])->withViewData(['preview' => $metadata->home()]);
    }

    private function signInLocalDeveloper(ShowLogRequest $request): RedirectResponse
    {
        $user = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local_dev', 'name' => 'Local Developer'],
        );

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }
}
