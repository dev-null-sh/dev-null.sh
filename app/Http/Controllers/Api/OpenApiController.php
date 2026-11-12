<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final readonly class OpenApiController
{
    /**
     * The OpenAPI spec of this API, as committed from what the code describes, for anyone building on it. Public and
     * unsigned, like the docs page that reads it. It is sent whole rather than streamed, so its ETag can be computed.
     */
    public function show(): Response
    {
        return response(File::get(resource_path('api/openapi.json')), headers: ['Content-Type' => 'application/json']);
    }
}
