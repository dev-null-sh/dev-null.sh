<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrg;
use App\Http\Requests\CreateApiOrgRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

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
}
