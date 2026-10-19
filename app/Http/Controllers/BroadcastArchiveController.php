<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationArchive;
use App\Actions\DeleteConversationArchive;
use App\Http\Requests\CreateBroadcastArchiveRequest;
use App\Http\Requests\DeleteBroadcastArchiveRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class BroadcastArchiveController
{
    public function store(CreateBroadcastArchiveRequest $request, #[CurrentUser] User $user, Conversation $broadcast, CreateConversationArchive $action): Response
    {
        $action->handle($user, $broadcast);

        return response()->noContent();
    }

    public function destroy(DeleteBroadcastArchiveRequest $request, #[CurrentUser] User $user, Conversation $broadcast, DeleteConversationArchive $action): Response
    {
        $action->handle($user, $broadcast);

        return response()->noContent();
    }
}
