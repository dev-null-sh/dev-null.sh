<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrgCall;
use App\Actions\DeleteOrgCall;
use App\Actions\UpdateOrgCall;
use App\Http\Requests\CreateApiOrgCallRequest;
use App\Http\Requests\DeleteApiOrgCallRequest;
use App\Http\Requests\UpdateApiOrgCallRequest;
use App\Http\Resources\CallMemberResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class OrgCallController
{
    /**
     * Starts the org's call, or joins it while it has room, as the web's Call and Join do. The call is null when it
     * filled up in between.
     */
    public function store(CreateApiOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, CreateOrgCall $action, OrgCallQuery $calls): JsonResponse
    {
        $action->handle($user, $org);

        $call = $calls->forMember($user, $org, $calls->get($org));

        return response()->json(['call' => $call === null ? null : [
            ...$call,
            'members' => CallMemberResource::collection($call['members']),
            'signals' => (object) $call['signals'],
        ]], 201)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Each member checks in every couple of seconds, handing over their signals and picking up the others'.
     */
    public function update(UpdateApiOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgCall $action, OrgCallQuery $calls): JsonResponse
    {
        $action->handle($user, $org, $request->signals());

        $call = $calls->forMember($user, $org, $request->call());

        return response()->json(['call' => $call === null ? null : [
            ...$call,
            'members' => CallMemberResource::collection($call['members']),
            'signals' => (object) $call['signals'],
        ]])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Leaves the org's call.
     */
    public function destroy(DeleteApiOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteOrgCall $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
