<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class QueueGitHubSshKeySync
{
    /**
     * A signed-in visit queues a refresh of that person's GitHub SSH keys, so a key added on GitHub shows up
     * without signing out. Polling, partial reloads (including one that only names props to leave out) and prefetches
     * are not visits, or a tab left open would ask GitHub forever. A request that asks for JSON, and a JSON response,
     * are not visits either, unless the request is a full Inertia visit, and a terminal request is not one. A visit
     * skips the queue when a sync just finished, and
     * otherwise only the first visit in a minute queues one. A failure is reported, never thrown: refreshing keys
     * must not break the page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        $isPageVisit = $request->isMethod('GET')
            && ! $request->prefetch()
            && ! $request->hasHeader('X-Inertia-Partial-Data')
            && ! $request->hasHeader('X-Inertia-Partial-Except')
            && ($request->hasHeader('X-Inertia') || (! $request->expectsJson() && ! $response instanceof JsonResponse));

        if ($user instanceof User && $response->isSuccessful() && ! $request->is('api/cli/*') && (! $request->isMethod('GET') || $isPageVisit)) {
            rescue(function () use ($user): void {
                if (Cache::has("ssh-keys-synced:{$user->id}")) {
                    return;
                }

                if (! Cache::add("ssh-keys-sync-queued:{$user->id}", true, now()->addMinute())) {
                    return;
                }

                dispatch(new SyncGitHubSshKeysJob($user));
            });
        }

        return $response;
    }
}
