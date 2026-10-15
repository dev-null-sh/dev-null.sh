<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowRepoPetRepositoryRequest;
use Illuminate\Http\JsonResponse;

final readonly class RepoPetRepositoryController
{
    public function show(ShowRepoPetRepositoryRequest $request): JsonResponse
    {
        return response()->json($request->options());
    }
}
