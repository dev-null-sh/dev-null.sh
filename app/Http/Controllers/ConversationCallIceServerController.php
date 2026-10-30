<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\TurnProvider;
use App\Http\Requests\ListConversationCallIceServersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final readonly class ConversationCallIceServerController
{
    public function index(ListConversationCallIceServersRequest $request, User $partner, TurnProvider $turn): JsonResponse
    {
        return response()->json([
            'ice_servers' => $turn->iceServers(),
            'is_relay_only' => $turn->isRelayOnly(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
