<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateApiBroadcastAuthorizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Broadcast;

final readonly class BroadcastAuthorizationController
{
    /**
     * An approved device joins its user's private channel, signed like any other API request.
     */
    public function store(CreateApiBroadcastAuthorizationRequest $request): JsonResponse
    {
        return response()->json(Broadcast::validAuthenticationResponse($request, true))
            ->header('Cache-Control', 'no-store, private');
    }
}
