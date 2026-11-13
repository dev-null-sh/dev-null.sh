<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CallMemberResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\CallQuery;
use App\Queries\OrgCallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CallController
{
    /**
     * The call ringing for the user and the calls going on in their orgs, polled as often as the web polls them.
     */
    public function show(#[CurrentUser] User $user, CallQuery $calls, OrgCallQuery $orgCalls): JsonResponse
    {
        $call = $calls->incoming($user);

        return response()->json([
            'call' => $call === null ? null : [...$call, 'partner' => UserResource::make($call['partner'])],
            'org_calls' => array_map(
                fn (array $orgCall): array => [...$orgCall, 'members' => CallMemberResource::collection($orgCall['members'])],
                $orgCalls->ongoing($user),
            ),
        ])->header('Cache-Control', 'private, no-store');
    }
}
