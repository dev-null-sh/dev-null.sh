<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiConversationsRequest;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class ConversationController
{
    /**
     * A page of the web's inbox, newest first, for the Open, Fetched or Stashed tab, matching a name or handle, and
     * continuing before a conversation, with the unread count the web's nav shows and how many requests are fetched.
     */
    public function index(ListApiConversationsRequest $request, #[CurrentUser] User $user, ConversationsQuery $conversations): AnonymousResourceCollection
    {
        $page = $conversations->get($user, $request->tab(), $request->term(), $request->beforeId());

        return ConversationResource::collection($page['conversations'])->additional([
            'has_more' => $page['has_more'],
            'unread_count' => $conversations->unreadCount($user),
            'requests_count' => $conversations->requestCount($user),
        ]);
    }
}
