<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversationArchive;
use App\Actions\DeleteConversationArchive;
use App\Http\Requests\CreateApiConversationArchiveRequest;
use App\Http\Requests\DeleteApiConversationArchiveRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationArchiveController
{
    /**
     * Stashes the conversation for the user only, as the web does. It pops back into Open when either of them writes.
     */
    public function store(CreateApiConversationArchiveRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationArchive $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }

    /**
     * Pops the conversation back into Open.
     */
    public function destroy(DeleteApiConversationArchiveRequest $request, #[CurrentUser] User $user, User $partner, DeleteConversationArchive $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }
}
