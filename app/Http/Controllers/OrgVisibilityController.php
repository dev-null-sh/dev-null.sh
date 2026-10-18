<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrgMemberVisibility;
use App\Http\Requests\UpdateOrgVisibilityRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgVisibilityController
{
    public function update(UpdateOrgVisibilityRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgMemberVisibility $action): Response
    {
        $action->handle($org, $user, $request->boolean('is_hidden'));

        return response()->noContent();
    }
}
