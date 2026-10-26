<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationMute;
use App\Http\Requests\UpdateOrgMuteRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgMuteController
{
    /**
     * Mutes the org for the user only, until the chosen duration ends or forever, or unmutes it.
     */
    public function update(UpdateOrgMuteRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationMute $action): JsonResponse
    {
        $until = $action->handle($user, $org, $request->duration());

        return response()->json(['muted_until_at' => $until?->toJSON()]);
    }
}
