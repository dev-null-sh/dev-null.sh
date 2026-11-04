<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\EventTheme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class HandleAppearance
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('eventTheme', EventTheme::forRequest($request)?->value);
        // What auto means today, so the settings page can apply it without a reload.
        View::share('autoEventTheme', EventTheme::autoForRequest($request)?->value);

        return $next($request);
    }
}
