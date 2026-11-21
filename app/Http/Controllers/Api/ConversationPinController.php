<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationPin;
use App\Http\Requests\UpdateApiConversationPinRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ConversationPinController
{
    public function update(UpdateApiConversationPinRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationPin $action): JsonResponse
    {
        $at = $action->handle($user, Conversation::betweenOrFail($user, $partner), $request->boolean('pinned'));

        return response()->json(['pinned_at' => $at?->toJSON()]);
    }
}
