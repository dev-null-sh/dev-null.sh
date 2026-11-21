<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListInboxRequest;
use App\Http\Resources\GuildBadges;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class InboxController
{
    public function index(ListInboxRequest $request, #[CurrentUser] User $user, ConversationsQuery $conversations): JsonResponse
    {
        $page = $conversations->get($user, $request->tab(), $request->term(), $request->beforeId());
        $notified = $request->notificationConversations() ? $conversations->get($user, pinnedFirst: false)['conversations'] : null;
        $badges = GuildBadges::forConversations($page['conversations']->concat($notified ?? []), $user);

        return response()->json([
            ...($request->notificationBaseline() ? ['notification_baseline' => $conversations->notificationBaseline($user)] : []),
            ...$page,
            'conversations' => $badges->onConversations($page['conversations']),
            ...($notified === null ? [] : ['notification_conversations' => $badges->onConversations($notified)]),
            'unread' => $conversations->unreadCount($user),
            'requests' => $conversations->requestCount($user),
        ])->header('Cache-Control', 'private, no-store');
    }
}
