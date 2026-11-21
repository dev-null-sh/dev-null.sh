<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class SerializePushSubscriptions
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        // Hold the account lock across validation and creation, including concurrent browser enrollments.
        return DB::transaction(function () use ($user, $request, $next): Response {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();

            return $next($request);
        });
    }
}
