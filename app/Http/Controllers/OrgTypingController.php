<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationTyping;
use App\Http\Requests\UpdateOrgTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgTypingController
{
    public function update(UpdateOrgTypingRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationTyping $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
