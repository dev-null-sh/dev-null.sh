<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteOrgMentions;
use App\Http\Requests\DeleteApiOrgMentionsRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgMentionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgMentionController
{
    /**
     * Answers with the mentions left, as the web does, so clearing also drops the ones the bell hadn't loaded yet.
     */
    public function destroy(DeleteApiOrgMentionsRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteOrgMentions $action, OrgMentionsQuery $mentions): JsonResponse
    {
        $action->handle($user, $org, $request->messageIds());
        $left = $mentions->get($user, $org);

        return response()->json([
            'mentions' => MessageResource::collection($left['mentions']),
            'mentions_count' => $left['mentions_count'],
        ]);
    }
}
