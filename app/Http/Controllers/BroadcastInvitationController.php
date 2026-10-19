<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBroadcastInvitation;
use App\Actions\DeleteBroadcastInvitation;
use App\Http\Requests\CreateBroadcastInvitationRequest;
use App\Http\Requests\DeleteBroadcastInvitationRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class BroadcastInvitationController
{
    public function store(CreateBroadcastInvitationRequest $request, Conversation $broadcast, CreateBroadcastInvitation $action): Response
    {
        $action->handle($broadcast, $request->member());

        return response()->noContent();
    }

    public function destroy(DeleteBroadcastInvitationRequest $request, Conversation $broadcast, User $invitee, DeleteBroadcastInvitation $action): Response
    {
        $action->handle($broadcast, $invitee);

        return response()->noContent();
    }
}
