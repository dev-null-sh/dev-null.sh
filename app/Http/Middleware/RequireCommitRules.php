<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCommitRules
{
    /**
     * A first-time poster reads the commit rules on the web before their first commit, so it has to come from the web
     * composer, which says they read them. After that, they can commit from anywhere.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        if ($user->commit_rules_accepted_at !== null) {
            return $next($request);
        }

        $fromWeb = ! $request->is('api/*');

        if ($fromWeb && $request->boolean('commit_rules_accepted')) {
            $user->update(['commit_rules_accepted_at' => now()]);

            return $next($request);
        }

        $message = __('Welcome! Your first commit has to come from the web, so you can read the commit rules first: :url. After that, commit from anywhere.', [
            'url' => url('/'),
        ]);

        // The `devnull commit` script prints plain text...
        if ($request->routeIs('cli-commit.store')) {
            return response($message."\n", 403)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // The mobile app reads JSON with a code...
        if (! $fromWeb) {
            return new ApiException(ApiErrorCode::CommitRulesRequired, $message)->render();
        }

        throw ValidationException::withMessages([
            'body' => __('Read the commit rules first: reload the page and commit again.'),
        ]);
    }
}
