<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use DateTimeZone;
use Illuminate\Http\Request;
use IntlTimeZone;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Browser time zones may use older IANA names that Laravel's primary timezone list rejects.
 */
final readonly class NormalizeTimezone
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timeZone = $request->input('timezone');

        if (is_string($timeZone)) {
            try {
                // DateTimeZone::getName() preserves aliases; ICU supplies the current IANA name.
                $iana = IntlTimeZone::getIanaID($timeZone);

                if ($iana !== false && in_array($iana, DateTimeZone::listIdentifiers(), true)) {
                    $request->merge(['timezone' => (new DateTimeZone($iana))->getName()]);
                }
            } catch (Throwable) {
                // Leave invalid input unchanged for the request's normal validation.
            }
        }

        return $next($request);
    }
}
