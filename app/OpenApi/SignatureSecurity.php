<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Says which endpoints need a signed request, which only read more with one, as the signature middleware's `optional`
 * variant marks them, and which need none, and how a request whose signature can't be trusted is answered.
 *
 * The middleware is named rather than imported: only App\Http may use App\Http\Middleware.
 */
final readonly class SignatureSecurity implements OperationTransformer
{
    private const string Middleware = 'AuthenticateSignedRequest';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = array_map(class_basename(...), array_filter($routeInfo->route->gatherMiddleware(), is_string(...)));
        $isOptional = in_array(self::Middleware.':optional', $middleware, true);
        $isRequired = in_array(self::Middleware, $middleware, true);
        $signed = new SecurityRequirement([ApiDocument::SecurityScheme => []]);

        $operation->security = $isOptional ? [new SecurityRequirement([]), $signed] : ($isRequired ? [$signed] : []);

        if ($isOptional || $isRequired) {
            $operation->addResponse(Response::make(401)
                ->setDescription($isOptional
                    ? 'The signature is stale or wrong, or the key that made it is not an approved device. Without one, the request reads as a guest.'
                    : 'The signature is missing, stale or wrong, or the key that made it is not an approved device.')
                ->setContent('application/json', ApiDocument::error()));
        }
    }
}
