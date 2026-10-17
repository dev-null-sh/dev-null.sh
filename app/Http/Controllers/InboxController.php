<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListInboxRequest;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class InboxController
{
    public function index(ListInboxRequest $request, #[CurrentUser] User $user, ConversationsQuery $conversations): JsonResponse
    {
        return response()->json([
            'conversations' => $conversations->get($user, $request->isArchived(), $request->term()),
            'unread' => $conversations->unreadCount($user),
        ])->header('Cache-Control', 'private, no-store');
    }
}
