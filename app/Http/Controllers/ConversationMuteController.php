<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationMute;
use App\Http\Requests\UpdateConversationMuteRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ConversationMuteController
{
    /**
     * Mutes the conversation for the user only, until the chosen duration ends or forever, or unmutes it.
     */
    public function update(UpdateConversationMuteRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationMute $action): JsonResponse
    {
        $until = $action->handle($user, Conversation::betweenOrFail($user, $partner), $request->duration());

        return response()->json(['muted_until_at' => $until?->toJSON()]);
    }
}
