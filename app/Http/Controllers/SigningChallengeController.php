<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateSigningChallengeRequest;
use App\Models\User;
use App\Services\PasskeyChallenges;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class SigningChallengeController
{
    public function store(CreateSigningChallengeRequest $request, #[CurrentUser] User $user, PasskeyChallenges $challenges): JsonResponse
    {
        return response()->json($challenges->issue($user, $request->statement()));
    }
}
