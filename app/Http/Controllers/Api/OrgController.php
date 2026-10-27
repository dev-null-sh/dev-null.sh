<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrg;
use App\Actions\UpdateOrgName;
use App\Http\Requests\CreateApiOrgRequest;
use App\Http\Requests\UpdateApiOrgRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class OrgController
{
    /**
     * Starts an org as the web's New org does: the user maintains it, and everyone they add contributes.
     */
    public function store(CreateApiOrgRequest $request, #[CurrentUser] User $user, CreateOrg $action): JsonResponse
    {
        $org = $action->handle($user, $request->string('name')->trim()->value() ?: null, $request->members());

        return response()->json(['org' => ['id' => $org->id]], 201);
    }

    /**
     * Renames the org, or takes its name away, as the web's org settings do.
     */
    public function update(UpdateApiOrgRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgName $action): Response
    {
        $action->handle($user, $org, $request->string('name')->trim()->value() ?: null);

        return response()->noContent();
    }
}
