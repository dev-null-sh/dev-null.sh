<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Dto\ReaderClock;
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
        $now = now(ReaderClock::of($request)->timeZone);
        $choice = $request->cookie('event_theme');

        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('eventTheme', EventTheme::current(is_string($choice) ? $choice : null, $now)?->value);
        // What auto means today, so the settings page can apply it without a reload.
        View::share('autoEventTheme', EventTheme::on($now)?->value);

        return $next($request);
    }
}
