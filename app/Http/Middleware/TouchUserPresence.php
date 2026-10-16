<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\UpdateUserLastActiveAt;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TouchUserPresence
{
    public function __construct(private UpdateUserLastActiveAt $updateUserLastActiveAt)
    {
        //
    }

    /**
     * Any authenticated request is the signal that a user is around; guests are simply skipped.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->updateUserLastActiveAt->handle($user);
        }

        return $next($request);
    }
}
