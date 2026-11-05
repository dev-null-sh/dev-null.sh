<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteOrgMentions;
use App\Http\Requests\DeleteOrgMentionsRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgMentionController
{
    public function destroy(DeleteOrgMentionsRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteOrgMentions $action): Response
    {
        $action->handle($user, $org, $request->messageId());

        return response()->noContent();
    }
}
