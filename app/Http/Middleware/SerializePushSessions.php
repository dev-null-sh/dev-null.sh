<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class SerializePushSessions
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->cookie(Config::string('session.cookie'));

        if (! is_string($sessionId)) {
            return $next($request);
        }

        // Run before StartSession and hold through its save; a cache lease can expire mid-enrollment.
        return DB::transaction(function () use ($sessionId, $request, $next): Response {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [hash('sha256', 'remote-push-session:'.$sessionId)]);

            return $next($request);
        });
    }
}
