<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiOrgMessagesRequest;
use App\Http\Resources\OrgThreadResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class OrgMessageController
{
    /**
     * An org's thread as the web loads and polls it, from where the user's history starts.
     */
    public function index(ListApiOrgMessagesRequest $request, #[CurrentUser] User $user, Conversation $org, ConversationThreadQuery $threads): OrgThreadResource
    {
        return OrgThreadResource::make([
            'org' => $org,
            ...$threads->org($user, $org, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
        ]);
    }
}
