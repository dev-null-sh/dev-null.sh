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
                // Older ICU releases lack getIanaID; keep common browser aliases working.
                $iana = match ($timeZone) {
                    'Asia/Calcutta' => 'Asia/Kolkata',
                    'Europe/Kiev' => 'Europe/Kyiv',
                    'US/Eastern' => 'America/New_York',
                    default => method_exists(IntlTimeZone::class, 'getIanaID')
                        ? IntlTimeZone::getIanaID($timeZone)
                        : IntlTimeZone::getCanonicalID($timeZone),
                };

                if ($iana !== false && in_array($iana, DateTimeZone::listIdentifiers(), true)) {
                    if ($request->query->has('timezone') && ! $request->request->has('timezone') && ! $request->json()->has('timezone')) {
                        $request->query->set('timezone', $iana);
                    } else {
                        $request->merge(['timezone' => $iana]);
                    }
                }
            } catch (Throwable) {
                // Leave invalid input unchanged for the request's normal validation.
            }
        }

        return $next($request);
    }
}
