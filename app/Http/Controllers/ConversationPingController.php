<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationPing;
use App\Http\Requests\CreateConversationPingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ConversationPingController
{
    public function store(CreateConversationPingRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationPing $action): JsonResponse
    {
        return response()->json(['message' => $action->handle($user, Conversation::betweenOrFail($user, $partner))], 201);
    }
}
