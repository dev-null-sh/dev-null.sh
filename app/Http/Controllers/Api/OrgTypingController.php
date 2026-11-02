<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationTyping;
use App\Http\Requests\UpdateApiOrgTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgTypingController
{
    /**
     * Says the user is typing in the org for a few seconds.
     */
    public function update(UpdateApiOrgTypingRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationTyping $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
