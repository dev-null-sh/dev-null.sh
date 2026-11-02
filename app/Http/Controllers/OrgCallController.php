<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrgCall;
use App\Actions\DeleteOrgCall;
use App\Actions\UpdateOrgCall;
use App\Http\Requests\CreateOrgCallRequest;
use App\Http\Requests\DeleteOrgCallRequest;
use App\Http\Requests\UpdateOrgCallRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class OrgCallController
{
    public function store(CreateOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, CreateOrgCall $action, OrgCallQuery $calls): JsonResponse
    {
        $action->handle($user, $org);

        return response()->json(['call' => $calls->forMember($user, $org, $calls->get($org))], 201)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Each member checks in every couple of seconds, handing over their signals and picking up the others'.
     */
    public function update(UpdateOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgCall $action, OrgCallQuery $calls): JsonResponse
    {
        $action->handle($user, $org, $request->signals());

        return response()->json(['call' => $calls->forMember($user, $org, $request->call())])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(DeleteOrgCallRequest $request, #[CurrentUser] User $user, Conversation $org, DeleteOrgCall $action): Response
    {
        $action->handle($user, $org);

        return response()->noContent();
    }
}
