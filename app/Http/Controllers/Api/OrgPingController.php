<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversationPing;
use App\Http\Requests\CreateApiOrgPingRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class OrgPingController
{
    /**
     * Pings everyone in the org, as the web's composer does.
     */
    public function store(CreateApiOrgPingRequest $request, #[CurrentUser] User $user, Conversation $org, CreateConversationPing $action): MessageResource
    {
        return MessageResource::make($action->handle($user, $org)->load('user'));
    }
}
