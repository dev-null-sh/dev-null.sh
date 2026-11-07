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
     * A signed-in page visit queues a refresh of that person's GitHub SSH keys, so a key added on GitHub shows up
     * without signing out. One refresh costs three GitHub requests on the shared hourly quota, so only a real page
     * load counts. A star or another action does not, nor does polling, a partial reload (including one that only
     * names props to leave out), a prefetch, or JSON. A full Inertia visit does, and a terminal request does not.
     * A visit skips the queue when a sync just finished, and otherwise only the first visit in an hour queues one.
     * Sync from GitHub on the profile still refreshes the keys right away. A failure is reported, never thrown:
     * refreshing keys must not break the page.
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

        if ($user instanceof User && $response->isSuccessful() && $isPageVisit && ! $request->is('api/cli/*')) {
            rescue(function () use ($user): void {
                if (Cache::has("ssh-keys-synced:{$user->id}")) {
                    return;
                }

                if (! Cache::add("ssh-keys-sync-queued:{$user->id}", true, now()->addHour())) {
                    return;
                }

                dispatch(new SyncGitHubSshKeysJob($user));
            });
        }

        return $response;
    }
}
