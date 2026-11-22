<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListInboxRequest;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class InboxController
{
    public function index(ListInboxRequest $request, #[CurrentUser] User $user, ConversationsQuery $conversations): JsonResponse
    {
        $inbox = $conversations->get($user, $request->tab(), $request->term(), $request->beforeId());
        $inbox['conversations'] = ConversationResource::collection($inbox['conversations'])->resolve($request);

        return response()->json([
            ...($request->notificationBaseline() ? ['notification_baseline' => $conversations->notificationBaseline($user)] : []),
            ...$inbox,
            ...($request->notificationConversations() ? [
                'notification_conversations' => ConversationResource::collection($conversations->get($user, pinnedFirst: false)['conversations'])->resolve($request),
            ] : []),
            'unread' => $conversations->unreadCount($user),
            'requests' => $conversations->requestCount($user),
        ])->header('Cache-Control', 'private, no-store');
    }
}
