<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use LogicException;

/**
 * A commit takes a poll only when it has one: its rules say `nullable`, then `required_with:poll` for its options and
 * duration. Scramble reads the required options inside it as the poll itself being required, and its options and
 * duration as optional, the other way round. Schemas are found by class name: Scramble keys them by their full name.
 */
final readonly class OptionalPolls implements DocumentTransformer
{
    private const array Requests = ['CreateApiCommitRequest', 'CreateApiScheduledCommitRequest'];

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach (self::Requests as $name) {
            $request = collect($document->components->schemas)->first(fn (mixed $schema, string $key): bool => preg_replace('/^.*[.\\\\]/', '', $key) === $name);
            $fields = $request instanceof Schema ? $request->type : null;
            $poll = $fields instanceof ObjectType ? $fields->properties['poll'] ?? null : null;

            throw_unless($fields instanceof ObjectType && $poll instanceof ObjectType, fn (): LogicException => new LogicException("{$name} no longer takes a poll object: update ".self::class.'.'));

            $fields->required = array_values(array_diff($fields->required, ['poll']));
            $poll->setRequired(['options', 'duration']);
            $poll->nullable(true);
        }
    }
}
