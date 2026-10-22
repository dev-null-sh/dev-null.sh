<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationStatus;
use App\Enums\RemoteStatus;
use App\Http\Requests\CreateApiConversationMergeRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationMergeController
{
    /**
     * Merges a fetched request into the user's open conversations, so they can reply.
     */
    public function store(CreateApiConversationMergeRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationStatus $action): Response
    {
        $action->handle($user, Conversation::betweenOrFail($user, $partner), RemoteStatus::Open);

        return response()->noContent();
    }
}
