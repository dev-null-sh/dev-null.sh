<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateBroadcastAuthorizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Broadcast;

final readonly class BroadcastAuthorizationController
{
    /**
     * The browser joins its user's private channel, where only that user's updates are sent.
     */
    public function store(CreateBroadcastAuthorizationRequest $request): JsonResponse
    {
        return response()->json(Broadcast::validAuthenticationResponse($request, true))
            ->header('Cache-Control', 'no-store, private');
    }
}
