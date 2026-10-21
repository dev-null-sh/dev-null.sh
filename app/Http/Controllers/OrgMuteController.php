<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationMute;
use App\Actions\DeleteConversationMute;
use App\Http\Requests\CreateOrgMuteRequest;
use App\Http\Requests\DeleteOrgMuteRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgMuteController
{
    public function store(CreateOrgMuteRequest $request, #[CurrentUser] User $user, Conversation $org, CreateConversationMute $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }

    public function destroy(DeleteOrgMuteRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteConversationMute $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
