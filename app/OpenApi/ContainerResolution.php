<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Tells Scramble that `resolve(Mentions::class)` is a Mentions, as the container makes it, so what resources build
 * with services they resolve is described instead of being left unknown.
 */
final readonly class ContainerResolution implements FunctionReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === 'resolve';
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        $abstract = $event->getArg('name', 0);

        return $abstract instanceof GenericClassStringType && $abstract->type instanceof ObjectType
            ? $abstract->type
            : null;
    }
}
