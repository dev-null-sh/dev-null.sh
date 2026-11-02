<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationPing;
use App\Http\Requests\CreateOrgPingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgPingController
{
    public function store(CreateOrgPingRequest $request, #[CurrentUser] User $user, Conversation $org, CreateConversationPing $action): JsonResponse
    {
        return response()->json(['message' => $action->handle($user, $org)->load('user')], 201);
    }
}
