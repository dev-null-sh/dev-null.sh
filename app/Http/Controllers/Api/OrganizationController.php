<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiOrganizationRequest;
use Illuminate\Http\JsonResponse;

final readonly class OrganizationController
{
    /**
     * A GitHub organization someone mentioned, as its page on the web shows it, so the app can open a mention of one.
     */
    public function show(ShowApiOrganizationRequest $request): JsonResponse
    {
        return response()->json(['data' => $request->organization()]);
    }
}
