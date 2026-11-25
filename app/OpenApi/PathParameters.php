<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Names each path parameter after what the route reads, `{handle}` for `users/{user:handle}`, and describes one read
 * by a field as that field, a string. Scramble names them after the controller's arguments of the same type, which
 * picks the signed-in user for a route about someone else, and describes them as the model's ID.
 */
final readonly class PathParameters implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        preg_match_all('/\{(\w+)\}/', $operation->path, $documented);
        preg_match_all('/\{(\w+)\??\}/', $routeInfo->route->uri(), $routed);

        $fields = $routeInfo->route->bindingFields();
        $parameters = [];

        foreach ($documented[1] as $index => $name) {
            $model = $routed[1][$index] ?? $name;
            $field = is_string($fields[$model] ?? null) ? $fields[$model] : null;
            $parameters[$name] = ['name' => $field ?? $model, 'field' => $field, 'model' => $model];
        }

        $names = array_column($parameters, 'name');
        $twice = array_diff_assoc($names, array_unique($names));

        throw_if($twice !== [], fn (): LogicException => new LogicException("Two path parameters of {$operation->path} would both be named {".reset($twice).'}.'));

        $operation->setPath((string) preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $match): string => '{'.($parameters[$match[1]]['name'] ?? $match[1]).'}',
            $operation->path,
        ));

        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->in !== 'path' || ! isset($parameters[$parameter->name])) {
                continue;
            }

            $routed = $parameters[$parameter->name];
            $parameter->name = $routed['name'];

            if ($routed['field'] !== null) {
                $schema = new Schema;
                $schema->type = new StringType;
                $parameter->setSchema($schema);
                $parameter->description = 'The '.Str::of($routed['model'])->snake(' ')->value()."'s {$routed['field']}";
            }
        }
    }
}
