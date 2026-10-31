<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CallController
{
    /**
     * The call ringing for the user, with the caller's offer to answer, until they answer it. The app polls it as often
     * as the web does, which is how a call rings while the app is open.
     */
    public function show(#[CurrentUser] User $user, CallQuery $calls): JsonResponse
    {
        $call = $calls->incoming($user);

        return response()->json([
            'call' => $call === null ? null : [
                'partner' => UserResource::make($call['partner']),
                'sdp' => $call['sdp'],
                'is_muted' => $call['is_muted'],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
