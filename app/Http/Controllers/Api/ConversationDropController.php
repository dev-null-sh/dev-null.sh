<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationStatus;
use App\Enums\RemoteStatus;
use App\Http\Requests\CreateApiConversationDropRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationDropController
{
    /**
     * Drops a fetched request for good, without telling the sender, whose request keeps waiting on their side.
     */
    public function store(CreateApiConversationDropRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationStatus $action): Response
    {
        $action->handle($user, Conversation::betweenOrFail($user, $partner), RemoteStatus::Dropped);

        return response()->noContent();
    }
}
