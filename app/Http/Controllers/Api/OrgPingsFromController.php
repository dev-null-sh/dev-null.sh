<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateOrgPingsFrom;
use App\Http\Requests\UpdateApiOrgPingsFromRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgPingsFromController
{
    /**
     * Chooses who can ping the org, as the web's org settings do.
     */
    public function update(UpdateApiOrgPingsFromRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgPingsFrom $action): Response
    {
        $action->handle($user, $org, $request->pingsFrom());

        return response()->noContent();
    }
}
