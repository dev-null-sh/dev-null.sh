<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedCommitsQuery;
use App\Services\Terminal\TerminalRenderer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LogController
{
    public function show(ShowLogRequest $request, #[CurrentUser] User $user, Log $log, SuggestedCommitsQuery $suggested, TerminalRenderer $renderer): Response|HttpResponse
    {
        if (TerminalRenderer::wantsCli($request)) {
            $limit = min(max($request->integer('limit', 20), 1), 50);

            $commits = $log->query()
                ->builder($user)
                ->take($limit)
                ->get();

            $color = TerminalRenderer::isColorSupported($request);
            $force16 = TerminalRenderer::is16Color($request);
            $content = $renderer->feed($commits, $color, $force16, title: "Log: {$log->value}");

            return response($content)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return Inertia::render('log/show', [
            'log' => $log,
            'commits' => Inertia::scroll(fn () => $log->query()
                ->builder($user)
                ->cursorPaginate(20)),
            'newCommitsCount' => Inertia::optional(function () use ($request, $user, $log): int {
                $seen = $request->since();

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
