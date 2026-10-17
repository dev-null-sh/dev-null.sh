<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final readonly class HandleLocale
{
    /**
     * The language is kept in a cookie, like the theme, so it applies before anyone signs in.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie('locale');
        $locale = is_string($cookie) ? Locale::tryFrom($cookie) : null;

        if ($locale instanceof Locale) {
            App::setLocale($locale->value);
        }

        return $next($request);
    }
}
