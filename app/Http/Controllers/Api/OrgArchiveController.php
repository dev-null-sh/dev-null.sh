<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversationArchive;
use App\Actions\DeleteConversationArchive;
use App\Http\Requests\CreateApiOrgArchiveRequest;
use App\Http\Requests\DeleteApiOrgArchiveRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgArchiveController
{
    /**
     * Stashes the org for the user only. It pops back into Open when anyone in it writes.
     */
    public function store(CreateApiOrgArchiveRequest $request, #[CurrentUser] User $user, Conversation $org, CreateConversationArchive $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }

    /**
     * Pops the org back into Open.
     */
    public function destroy(DeleteApiOrgArchiveRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteConversationArchive $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
