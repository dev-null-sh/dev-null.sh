<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteBroadcastMember;
use App\Actions\UpdateBroadcastMember;
use App\Http\Requests\DeleteBroadcastMemberRequest;
use App\Http\Requests\ListBroadcastMembersRequest;
use App\Http\Requests\UpdateBroadcastMemberRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\BroadcastMembersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class BroadcastMemberController
{
    public function index(ListBroadcastMembersRequest $request, Conversation $broadcast, BroadcastMembersQuery $query): JsonResponse
    {
        return response()->json($query->get($broadcast, $request->term(), $request->afterId()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateBroadcastMemberRequest $request, Conversation $broadcast, User $member, UpdateBroadcastMember $action): Response
    {
        $action->handle($broadcast, $member, $request->status());

        return response()->noContent();
    }

    public function destroy(DeleteBroadcastMemberRequest $request, Conversation $broadcast, User $member, DeleteBroadcastMember $action): Response
    {
        $action->handle($broadcast, $member);

        return response()->noContent();
    }
}
