<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\SyncGitHubProfileJob;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions are remembered, so people rarely sign in again: their GitHub profile is refreshed in the background instead, at most once a day.
 */
final readonly class SyncGitHubProfile
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->isStale($user)) {
            dispatch(new SyncGitHubProfileJob($user));
        }

        return $next($request);
    }

    /**
     * The local developer that auto-login creates has no GitHub account to refresh from.
     */
    private function isStale(User $user): bool
    {
        if (! ctype_digit($user->github_id)) {
            return false;
        }

        return $user->github_synced_at === null || $user->github_synced_at->lte(now()->subDay());
    }
}
