<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApiDocsSection;
use App\Queries\ApiDocsQuery;
use Illuminate\Http\JsonResponse;

final readonly class ApiDocsSchemaController
{
    /**
     * The JSON schemas of an endpoint of the API docs, which the page asks for when a reader picks the schema view, so
     * they never weigh on the page itself. The route only lets a section through, unbound since the route skips the
     * web group.
     */
    public function show(string $section, string $endpoint, ApiDocsQuery $query): JsonResponse
    {
        $schemas = $query->schemas(ApiDocsSection::from($section), $endpoint);

        return $schemas === null
            ? response()->json(['message' => 'Not found.'], 404)
            : response()->json($schemas);
    }
}
