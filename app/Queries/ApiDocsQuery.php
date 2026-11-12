<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ApiDocsSection;
use App\Services\ApiExamples;
use Closure;
use Composer\InstalledVersions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use stdClass;
use Tempest\Highlight\Highlighter;

/**
 * The endpoints of a group of the API docs, read from the committed OpenAPI spec and shaped for reading: every schema
 * it refers to resolved into fields, as deep as a reader needs, and a resource already being described named rather
 * than described again. Each group is cached until the spec, or how this reads it, changes, so a deploy never serves
 * a group shaped by the code before it, under one key that a new version replaces rather than adds to.
 *
 * @phpstan-type Field array{name: string, type: string, required: bool, description: string|null, enum: list<string|int>|null, children: list<array<string, mixed>>}
 * @phpstan-type Parameter array{in: string, name: string, type: string, required: bool, description: string|null, enum: list<string|int>|null, children: list<array<string, mixed>>}
 * @phpstan-type Schema array{code: string, html: string}
 * @phpstan-type Endpoint array{
 *     id: string,
 *     method: string,
 *     path: string,
 *     summary: string,
 *     description: string|null,
 *     signature: 'required'|'optional',
 *     parameters: list<Parameter>,
 *     body: array{mediaType: string, fields: list<Field>}|null,
 *     responses: list<array{status: string, description: string, fields: list<Field>}>,
 *     examples: list<array{label: string, language: string, code: string, html: string}>
 * }
 */
final readonly class ApiDocsQuery
{
    private const int Depth = 3;

    private const array SchemaKeywords = ['additionalProperties', 'items', 'additionalItems', 'unevaluatedProperties', 'unevaluatedItems', 'contains', 'propertyNames', 'not', 'if', 'then', 'else'];

    private const array SchemaMapKeywords = ['properties', 'patternProperties', 'dependentSchemas', '$defs'];

    private const array SchemaListKeywords = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

    /**
     * What the cached docs were made from: the spec, the code that reads and highlights it. Any of them changing makes
     * the cache stale.
     */
    private string $version;

    public function __construct(private ApiExamples $examples, private Highlighter $highlighter, private ?string $path = null)
    {
        $files = [$this->specPath(), __FILE__, (string) new ReflectionClass(ApiExamples::class)->getFileName(), (string) new ReflectionClass(ApiDocsSection::class)->getFileName()];

        $this->version = implode(':', [
            ...array_map(fn (string $file): string => (string) hash_file('xxh128', $file), $files),
            InstalledVersions::getReference('tempest/highlight') ?? '',
        ]);
    }

    /**
     * @return list<Endpoint>
     */
    public function get(ApiDocsSection $section): array
    {
        return $this->remember("api-docs:{$section->value}", fn (): array => $this->endpoints($this->spec(), $section));
    }

    /**
     * The JSON schemas of an endpoint's body and of the answers whose fields its docs detail, by status. They are
     * only read for the endpoint a reader asks them for, and only for an endpoint the group has, so a made-up one
     * costs nothing to keep.
     *
     * @return array{body: Schema|null, responses: array<string, Schema>}|null
     */
    public function schemas(ApiDocsSection $section, string $id): ?array
    {
        if (! in_array($id, array_column($this->get($section), 'id'), true)) {
            return null;
        }

        return $this->remember("api-docs-schemas:{$section->value}:{$id}", fn (): array => $this->endpointSchemas($this->spec(), $section, $id));
    }

    /**
     * Every endpoint of the reference in a line, for searching them from any page of the docs.
     *
     * @return list<array{id: string, method: string, path: string, summary: string, section: string}>
     */
    public function index(): array
    {
        return $this->remember('api-docs-index', fn (): array => array_merge(...array_map(fn (ApiDocsSection $section): array => array_map(fn (array $endpoint): array => [
            'id' => $endpoint['id'],
            'method' => $endpoint['method'],
            'path' => $endpoint['path'],
            'summary' => $endpoint['summary'],
            'section' => $section->value,
        ], $this->get($section)), ApiDocsSection::reference())));
    }

    /**
     * What a key holds for this version, made again and put in its place when it was made from another.
     *
     * @template T
     *
     * @param  Closure(): T  $make
     * @return T
     */
    private function remember(string $key, Closure $make): mixed
    {
        $fresh = fn (): array => ['version' => $this->version, 'data' => $make()];
        $entry = Cache::rememberForever($key, $fresh);

        if ($entry['version'] !== $this->version) {
            $entry = $fresh();
            Cache::forever($key, $entry);
        }

        return $entry['data'];
    }

    private function specPath(): string
    {
        return $this->path ?? resource_path('api/openapi.json');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function spec(): array
    {
        return $this->map(json_decode(File::get($this->specPath()), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $spec
     * @return array{body: Schema|null, responses: array<string, Schema>}
     */
    private function endpointSchemas(array $spec, ApiDocsSection $section, string $id): array
    {
        $schemas = $this->map($this->map($spec['components'] ?? null)['schemas'] ?? null);
        $responses = $this->map($this->map($spec['components'] ?? null)['responses'] ?? null);
        $operation = $this->operations($spec, $section)[$id]['operation'] ?? [];
        $answers = [];

        foreach ($this->map($operation['responses'] ?? null) as $status => $response) {
            [, $schema, $detailed] = $this->answer((string) $status, $this->map($response), $responses);

            if ($detailed && ($written = $this->schema($schema, $schemas)) !== null) {
                $answers[(string) $status] = $written;
            }
        }

        return [
            'body' => $this->schema($this->map($this->map(Arr::first($this->map($this->map($operation['requestBody'] ?? null)['content'] ?? null)))['schema'] ?? null), $schemas),
            'responses' => $answers,
        ];
    }

    /**
     * The operations of a group by the ID the docs give them, the spec's own or one made from the method and path, so
     * the reference and an endpoint's schemas find it by the same name.
     *
     * @param  array<array-key, mixed>  $spec
     * @return array<string, array{path: string, method: string, operation: array<array-key, mixed>}>
     */
    private function operations(array $spec, ApiDocsSection $section): array
    {
        $operations = [];
        $anchors = [];

        foreach ($this->map($spec['paths'] ?? null) as $path => $item) {
            foreach ($this->map($item) as $method => $operation) {
                $operation = $this->map($operation);

                if (in_array($section->title(), $this->map($operation['tags'] ?? null), true)) {
                    $id = $this->text($operation['operationId'] ?? null) ?? $method.'-'.mb_trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $path), '-');

                    $anchor = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $id);

                    throw_if(isset($anchors[$anchor]), fn (): LogicException => new LogicException("Two endpoints of the API docs would both be named {$anchor}."));

                    $anchors[$anchor] = true;

                    $operations[$id] = ['path' => (string) $path, 'method' => (string) $method, 'operation' => $operation];
                }
            }
        }

        return $operations;
    }

    /**
     * @param  array<array-key, mixed>  $spec
     * @return list<Endpoint>
     */
    private function endpoints(array $spec, ApiDocsSection $section): array
    {
        $schemas = $this->map($this->map($spec['components'] ?? null)['schemas'] ?? null);
        $responses = $this->map($this->map($spec['components'] ?? null)['responses'] ?? null);
        $operations = $this->operations($spec, $section);

        return array_map(
            fn (string $id, array $entry): array => $this->endpoint($id, $entry['path'], $entry['method'], $entry['operation'], $responses, $schemas),
            array_keys($operations),
            array_values($operations),
        );
    }

    /**
     * @param  array<array-key, mixed>  $operation
     * @param  array<array-key, mixed>  $responses
     * @param  array<array-key, mixed>  $schemas
     * @return Endpoint
     */
    private function endpoint(string $id, string $path, string $method, array $operation, array $responses, array $schemas): array
    {
        $body = $this->map($this->map($operation['requestBody'] ?? null)['content'] ?? null);
        $mediaType = array_key_first($body);

        $endpoint = [
            'id' => $id,
            'method' => mb_strtoupper($method),
            'path' => $path,
            'summary' => $this->text($operation['summary'] ?? null) ?? '',
            'description' => $this->text($operation['description'] ?? null) ?: null,
            'signature' => $this->map($operation['security'] ?? null) === [] || in_array([], $this->map($operation['security']), true) ? 'optional' : 'required',
            'parameters' => array_map(fn (mixed $parameter): array => $this->parameter($parameter, $schemas), array_values($this->map($operation['parameters'] ?? null))),
            'body' => $mediaType === null ? null : [
                'mediaType' => (string) $mediaType,
                'fields' => $this->fields($this->map($this->map($body[$mediaType])['schema'] ?? null), $schemas, 0, []),
            ],
            'responses' => array_map(
                fn (mixed $status, mixed $response): array => $this->response((string) $status, $this->map($response), $responses, $schemas),
                array_keys($this->map($operation['responses'] ?? null)),
                array_values($this->map($operation['responses'] ?? null)),
            ),
        ];

        return [...$endpoint, 'examples' => $this->examples->for($endpoint)];
    }

    /**
     * An answer, with the fields it holds when it succeeds, or when it is an error only this endpoint answers with.
     * The errors every endpoint shares, the API's own and Laravel's, are left to the Errors guide.
     *
     * @param  array<array-key, mixed>  $response
     * @param  array<array-key, mixed>  $responses
     * @param  array<array-key, mixed>  $schemas
     * @return array{status: string, description: string, fields: list<Field>}
     */
    private function response(string $status, array $response, array $responses, array $schemas): array
    {
        [$response, $schema, $detailed] = $this->answer($status, $response, $responses);

        return [
            'status' => $status,
            'description' => $this->text($response['description'] ?? null) ?? '',
            'fields' => $detailed ? $this->fields($schema, $schemas, 0, []) : [],
        ];
    }

    /**
     * An answer as the spec gives it, a shared one followed to its component, its schema, and whether the docs detail
     * it: a success always, an error only when it is this endpoint's own.
     *
     * @param  array<array-key, mixed>  $response
     * @param  array<array-key, mixed>  $responses
     * @return array{array<array-key, mixed>, array<array-key, mixed>, bool}
     */
    private function answer(string $status, array $response, array $responses): array
    {
        $shared = isset($response['$ref']);
        $response = $shared ? $this->map($responses[Str::afterLast($this->text($response['$ref']) ?? '', '/')] ?? null) : $response;
        $schema = $this->map($this->map(Arr::first($this->map($response['content'] ?? null)))['schema'] ?? null);

        return [$response, $schema, str_starts_with($status, '2') || (! $shared && ! isset($schema['$ref']))];
    }

    /**
     * The JSON schema of a body or an answer as a reader can follow it, on its own: the schema itself, and every
     * schema it refers to written once in its `$defs`, so a commit's parent points back at Commit instead of looping,
     * and a resource used in many places weighs once.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     * @return Schema|null
     */
    private function schema(array $schema, array $schemas): ?array
    {
        if ($schema === []) {
            return null;
        }

        $defs = [];
        $root = $this->inline($schemas[Str::afterLast($this->text($schema['$ref'] ?? null) ?? '', '/')] ?? $schema, $schemas, $defs);
        $document = $defs === [] ? $root : [...$this->map($root), '$defs' => $defs];
        $code = (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['code' => $code, 'html' => $this->highlighter->parse($code, 'json')];
    }

    /**
     * A schema with its references pointing at `$defs`, and each schema they name written there once.
     *
     * @param  array<array-key, mixed>  $schemas
     * @param  array<string, mixed>  $defs
     */
    private function inline(mixed $schema, array $schemas, array &$defs, string $kind = 'schema'): mixed
    {
        if (! is_array($schema)) {
            return $schema;
        }

        if ($schema === []) {
            return in_array($kind, ['schema', 'map'], true) ? new stdClass : [];
        }

        $reference = $kind === 'schema' ? $this->text($schema['$ref'] ?? null) : null;

        if ($reference === null) {
            foreach ($schema as $key => $value) {
                $schema[$key] = $this->inline($value, $schemas, $defs, $this->kindOf($kind, (string) $key));
            }

            return $schema;
        }

        $name = Str::afterLast($reference, '/');

        if (isset($schemas[$name]) && ! array_key_exists($name, $defs)) {
            $defs[$name] = null;
            $defs[$name] = $this->inline($schemas[$name], $schemas, $defs);
        }

        return ['$ref' => isset($schemas[$name]) ? "#/\$defs/{$name}" : $reference];
    }

    /**
     * What a value under a key is, in JSON Schema terms: a schema, a map or a list of schemas, or plain data. The spec
     * is read as PHP arrays, which can't tell `{}` from `[]`, so an empty schema or map is written back as an object
     * and anything else empty as a list.
     */
    private function kindOf(string $parent, string $key): string
    {
        return match (true) {
            $parent === 'map', $parent === 'list' => 'schema',
            $parent !== 'schema' => 'data',
            in_array($key, self::SchemaKeywords, true) => 'schema',
            in_array($key, self::SchemaMapKeywords, true) => 'map',
            in_array($key, self::SchemaListKeywords, true) => 'list',
            default => 'data',
        };
    }

    /**
     * A path or query parameter. A path always has its parameters, so none of them is ever null.
     *
     * @param  array<array-key, mixed>  $schemas
     * @return Parameter
     */
    private function parameter(mixed $parameter, array $schemas): array
    {
        $parameter = $this->map($parameter);
        $in = $this->text($parameter['in'] ?? null) ?? 'query';
        $field = $this->field(
            $this->text($parameter['name'] ?? null) ?? '',
            [...$this->map($parameter['schema'] ?? null), 'description' => $parameter['description'] ?? null],
            ($parameter['required'] ?? false) === true,
            $schemas,
            0,
            [],
        );

        return [
            'in' => $in,
            ...$field,
            'type' => $in === 'path' ? Str::replace(' | null', '', $field['type']) : $field['type'],
        ];
    }

    /**
     * The fields of an object, those of every schema it is all of, or of any it may be, which are only required when
     * every one of them requires it.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     * @param  list<string>  $seen
     * @return list<Field>
     */
    private function fields(array $schema, array $schemas, int $depth, array $seen): array
    {
        [$schema, $seen] = $this->resolve($schema, $schemas, $seen);
        $through = [];

        while (isset($schema['items'])) {
            $items = $this->map($schema['items']);
            $names = $this->refers($items, $schemas);

            if (array_intersect($names, $through) !== []) {
                return [];
            }

            $through = [...$through, ...$names];
            [$schema, $seen] = $this->resolve($items, $schemas, $seen);
        }

        $parts = array_map(fn (mixed $part): array => $this->resolve($this->map($part), $schemas, $seen)[0], $this->map($schema['allOf'] ?? $schema['anyOf'] ?? $schema['oneOf'] ?? null));

        if ($parts !== []) {
            $required = isset($schema['allOf'])
                ? array_merge(...array_map(fn (array $part): array => $this->map($part['required'] ?? null), $parts))
                : array_intersect(...array_map(fn (array $part): array => $this->map($part['required'] ?? null), $parts));

            $properties = array_merge(...array_map(fn (array $part): array => $this->map($part['properties'] ?? null), $parts));

            return $this->properties($properties, array_values($required), $schemas, $depth, $seen);
        }

        return $this->properties($this->map($schema['properties'] ?? null), array_values($this->map($schema['required'] ?? null)), $schemas, $depth, $seen);
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @param  list<mixed>  $required
     * @param  array<array-key, mixed>  $schemas
     * @param  list<string>  $seen
     * @return list<Field>
     */
    private function properties(array $properties, array $required, array $schemas, int $depth, array $seen): array
    {
        return array_map(
            fn (mixed $name, mixed $schema): array => $this->field((string) $name, $this->map($schema), in_array($name, $required, true), $schemas, $depth, $seen),
            array_keys($properties),
            array_values($properties),
        );
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     * @param  list<string>  $seen
     * @return Field
     */
    private function field(string $name, array $schema, bool $required, array $schemas, int $depth, array $seen): array
    {
        $resolved = $this->resolve($schema, $schemas, $seen)[0];
        $refers = $this->refers($schema, $schemas);
        $describable = $depth + 1 < self::Depth && ! array_intersect($refers, $seen) && $this->isObject($resolved, $schemas);

        return [
            'name' => $name,
            'type' => $this->type($schema, $schemas),
            'required' => $required,
            'description' => $this->text($schema['description'] ?? null) ?? $this->text($resolved['description'] ?? null),
            'enum' => isset($resolved['enum']) || isset($resolved['const'])
                ? array_values(array_filter($this->map($resolved['enum'] ?? [$resolved['const']]), fn (mixed $value): bool => is_string($value) || is_int($value)))
                : null,
            'children' => $describable ? $this->fields($schema, $schemas, $depth + 1, [...$seen, ...$refers]) : [],
        ];
    }

    /**
     * Follows a reference to the schema it names, and remembers that schema is being described.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     * @param  list<string>  $seen
     * @return array{array<array-key, mixed>, list<string>}
     */
    private function resolve(array $schema, array $schemas, array $seen): array
    {
        $reference = $this->text($schema['$ref'] ?? null);

        if ($reference === null) {
            return [$schema, $seen];
        }

        $name = Str::afterLast($reference, '/');

        return [$this->map($schemas[$name] ?? null), [...$seen, $name]];
    }

    /**
     * The named schemas a schema is, or may be, itself or as the items of its list.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     * @return list<string>
     */
    private function refers(array $schema, array $schemas): array
    {
        if (($reference = $this->text($schema['$ref'] ?? null)) !== null) {
            return [Str::afterLast($reference, '/')];
        }

        if (isset($schema['items'])) {
            return $this->refers($this->map($schema['items']), $schemas);
        }

        return array_merge(...array_map(fn (mixed $part): array => $this->refers($this->map($part), $schemas), array_values($this->map($schema['anyOf'] ?? $schema['oneOf'] ?? null))));
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     */
    private function isObject(array $schema, array $schemas): bool
    {
        if (isset($schema['items'])) {
            return $this->isObject($this->resolve($this->map($schema['items']), $schemas, [])[0], $schemas);
        }

        return isset($schema['properties']) || isset($schema['allOf']) || isset($schema['anyOf']) || isset($schema['oneOf']);
    }

    /**
     * What a schema is, as a reader would say it: `uuid`, `string | null`, `array of Commit`, `User | Organization`.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $schemas
     */
    private function type(array $schema, array $schemas): string
    {
        if (($reference = $this->text($schema['$ref'] ?? null)) !== null) {
            return Str::of($reference)->afterLast('/')->replaceEnd('Resource', '')->value();
        }

        $parts = $this->map($schema['anyOf'] ?? $schema['oneOf'] ?? null);

        if ($parts !== []) {
            return implode(' | ', array_unique(array_map(fn (mixed $part): string => $this->type($this->map($part), $schemas), $parts)));
        }

        if (isset($schema['allOf'])) {
            return 'object';
        }

        $types = is_array($schema['type'] ?? null) ? $schema['type'] : [$schema['type'] ?? 'object'];

        return implode(' | ', array_map(fn (mixed $type): string => match (true) {
            $type === 'array' => 'array of '.$this->items($this->type($this->map($schema['items'] ?? null), $schemas)),
            $type === 'string' && is_string($schema['format'] ?? null) => $schema['format'],
            default => is_string($type) ? $type : 'mixed',
        }, $types));
    }

    /**
     * What a list holds, in parentheses when it may be one of several, so `array of (string | null) | null` reads as
     * a list that may be null of strings that may be null.
     */
    private function items(string $type): string
    {
        return str_contains($type, ' | ') ? "({$type})" : $type;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
