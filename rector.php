<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use RectorLaravel\Rector\Class_\AddHasFactoryToModelsRector;
use RectorLaravel\Rector\If_\ThrowIfRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withComposerBased(laravel: true)
    ->withCache(
        cacheDirectory: __DIR__.'/tmp/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Controller arguments trigger Form Request authorization through container resolution.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/app/Http/Controllers/*'],
        // Provider exception chains can contain request text and credentials.
        ThrowWithPreviousExceptionRector::class => [__DIR__.'/app/Jobs/ProcessExplore.php', __DIR__.'/app/Jobs/SummarizeExplore.php'],
        // These models are created through actions and have no factory.
        AddHasFactoryToModelsRector::class => [__DIR__.'/app/Models/ExploreTopic.php', __DIR__.'/app/Models/ExplorePreference.php'],
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        ThrowIfRector::class => [__DIR__.'/app/Actions/*'],
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets();
