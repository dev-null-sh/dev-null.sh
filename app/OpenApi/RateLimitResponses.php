<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Says how an endpoint answers once its rate limit is reached: always a message, with the API's code only for a signed
 * request, since unsigned requests keep Laravel's answer and a few limits answer in their own words.
 */
final readonly class RateLimitResponses implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (collect($routeInfo->route->gatherMiddleware())->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::startsWith($middleware, 'throttle:'))) {
            $operation->addResponse(Response::make(429)
                ->setDescription('Too many requests. Wait for the `Retry-After` seconds before trying again.')
                ->setContent('application/json', ApiDocument::rateLimitError()));
        }
    }
}
