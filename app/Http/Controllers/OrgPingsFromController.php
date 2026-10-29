<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOrgPingsFrom;
use App\Http\Requests\UpdateOrgPingsFromRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgPingsFromController
{
    public function update(UpdateOrgPingsFromRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgPingsFrom $action): Response
    {
        $action->handle($user, $org, $request->pingsFrom());

        return response()->noContent();
    }
}
