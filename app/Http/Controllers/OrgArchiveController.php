<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationArchive;
use App\Actions\DeleteConversationArchive;
use App\Http\Requests\CreateOrgArchiveRequest;
use App\Http\Requests\DeleteOrgArchiveRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgArchiveController
{
    public function store(CreateOrgArchiveRequest $request, #[CurrentUser] User $user, Conversation $org, CreateConversationArchive $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }

    public function destroy(DeleteOrgArchiveRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteConversationArchive $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
