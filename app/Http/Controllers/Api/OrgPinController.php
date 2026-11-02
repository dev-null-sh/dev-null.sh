<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationPin;
use App\Http\Requests\UpdateApiOrgPinRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgPinController
{
    public function update(UpdateApiOrgPinRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationPin $action): JsonResponse
    {
        $at = $action->handle($user, $org, $request->boolean('pinned'));

        return response()->json(['pinned_at' => $at?->toJSON()]);
    }
}
