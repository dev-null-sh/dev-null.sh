<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationArchive;
use App\Actions\DeleteConversationArchive;
use App\Http\Requests\CreateConversationArchiveRequest;
use App\Http\Requests\DeleteConversationArchiveRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class ConversationArchiveController
{
    public function store(CreateConversationArchiveRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationArchive $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }

    public function destroy(DeleteConversationArchiveRequest $request, #[CurrentUser] User $user, User $partner, DeleteConversationArchive $action): Response
    {
        $conversation = Conversation::between($user, $partner);

        if ($conversation instanceof Conversation) {
            $action->handle($user, $conversation);
        }

        return response()->noContent();
    }
}
