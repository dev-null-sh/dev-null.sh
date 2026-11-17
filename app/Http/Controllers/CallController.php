<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\CallQuery;
use App\Queries\OrgCallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CallController
{
    public function show(#[CurrentUser] User $user, CallQuery $calls, OrgCallQuery $orgCalls): JsonResponse
    {
        return response()->json([
            'call' => $calls->incoming($user),
            'org_calls' => $orgCalls->ongoing($user),
        ])->header('Cache-Control', 'private, no-store');
    }
}
