<?php

declare(strict_types=1);

namespace App\Providers;

use App\OpenApi\ApiDocument;
use App\OpenApi\ApiRoutes;
use App\OpenApi\ApiTags;
use App\OpenApi\CommitBodyLengthRule;
use App\OpenApi\ContainerResolution;
use App\OpenApi\OptionalPolls;
use App\OpenApi\PathParameters;
use App\OpenApi\RateLimitResponses;
use App\OpenApi\SignatureSecurity;
use App\OpenApi\SignedUploads;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * Describes the v1 API with Scramble, which only runs in development: the spec it exports is committed, and the API
 * docs page reads that file. Its own docs page is left out, since /docs/api is ours.
 *
 * It is always listed and does nothing without Scramble, rather than listed only when Scramble is installed:
 * make:provider rewrites bootstrap/providers.php as a plain list, which would register it in production.
 */
final class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(Scramble::class)) {
            Scramble::ignoreDefaultRoutes();
        }
    }

    public function boot(): void
    {
        if (class_exists(Scramble::class)) {
            $this->configure();
        }
    }

    private function configure(): void
    {
        Scramble::resolveTagsUsing(new ApiTags);
        Scramble::registerExtension(ContainerResolution::class);

        $config = Scramble::configure();

        $config->routes(new ApiRoutes()(...));
        $config->withTags(ApiTags::tags());
        $config->withOperationTransformers([PathParameters::class, SignatureSecurity::class, RateLimitResponses::class]);
        $config->withRuleTransformers(CommitBodyLengthRule::class);
        $config->withDocumentTransformers([ApiDocument::class, SignedUploads::class, OptionalPolls::class]);
    }
}
