<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteOrgMentions;
use App\Http\Requests\DeleteOrgMentionsRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgMentionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgMentionController
{
    /**
     * Answers with the mentions left, so clearing also drops the ones the bell hadn't loaded yet.
     */
    public function destroy(DeleteOrgMentionsRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteOrgMentions $action, OrgMentionsQuery $mentions): JsonResponse
    {
        $action->handle($user, $org, $request->messageIds());

        return response()->json($mentions->get($user, $org));
    }
}
