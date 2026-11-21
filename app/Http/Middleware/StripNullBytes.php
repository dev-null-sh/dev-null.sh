<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\NullBytes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes NUL bytes out of every request's inputs before anything reads them. Signed payloads that fill the request
 * later go through the same cleaning where they are read.
 */
final readonly class StripNullBytes
{
    public function __construct(private NullBytes $nullBytes)
    {
        //
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->nullBytes->strip($request);

        return $next($request);
    }
}
