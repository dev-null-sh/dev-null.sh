<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationPin;
use App\Http\Requests\UpdateConversationPinRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ConversationPinController
{
    public function update(UpdateConversationPinRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationPin $action): JsonResponse
    {
        $at = $action->handle($user, Conversation::betweenOrFail($user, $partner), $request->boolean('pinned'));

        return response()->json(['pinned_at' => $at?->toJSON()]);
    }
}
