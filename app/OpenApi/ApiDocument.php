<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Enums\ApiErrorCode;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * What every endpoint shares: how a request is signed, and the shape of an error from the signed API.
 */
final readonly class ApiDocument implements DocumentTransformer
{
    public const string SecurityScheme = 'devnullSignature';

    /**
     * The schema of an error from the signed API, as a response refers to it.
     */
    public static function error(): Schema
    {
        return self::schema(Reference::fromUri('#/components/schemas/ApiError'));
    }

    /**
     * The answer to too many requests, as a response refers to it.
     */
    public static function rateLimitError(): Schema
    {
        return self::schema(Reference::fromUri('#/components/schemas/RateLimitError'));
    }

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->components->addSecurityScheme(self::SecurityScheme, new ApiKeySecurityScheme('header', 'X-SSH-Signature')
            ->as('apiKey')
            ->setDescription("An SSH signature, in the `devnull-api` namespace, of the request's `X-Devnull-Timestamp`, `X-Devnull-Nonce`, method, path and query, and body, one per line, made with a key the user approved as a device."));

        $error = new ObjectType;
        $error->addProperty('message', new StringType()->setDescription('What went wrong, as the web says it.'));
        $error->addProperty('code', new StringType()
            ->enum(array_column(ApiErrorCode::cases(), 'value'))
            ->setDescription('Which error it is. A code never changes once released.'));
        $error->setRequired(['message', 'code']);

        $document->components->addSchema('ApiError', self::schema($error));

        $rateLimited = new ObjectType;
        $rateLimited->addProperty('message', new StringType()->setDescription('How long to wait, or which limit was reached.'));
        $rateLimited->addProperty('code', new StringType()
            ->enum([ApiErrorCode::RateLimited->value])
            ->setDescription('Only a signed request gets it, and never from the pings and commit search limits.'));
        $rateLimited->setRequired(['message']);

        $document->components->addSchema('RateLimitError', self::schema($rateLimited));
    }

    private static function schema(Type $type): Schema
    {
        $schema = new Schema;
        $schema->type = $type;

        return $schema;
    }
}
