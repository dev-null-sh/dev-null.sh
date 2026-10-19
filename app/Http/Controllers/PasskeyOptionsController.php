<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Passkeys;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class PasskeyOptionsController
{
    public function store(#[CurrentUser] User $user, Passkeys $passkeys): JsonResponse
    {
        return response()->json($passkeys->creationOptions($user));
    }
}
