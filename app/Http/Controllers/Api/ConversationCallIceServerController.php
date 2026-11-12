<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\TurnProvider;
use App\Http\Requests\ListApiConversationCallIceServersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final readonly class ConversationCallIceServerController
{
    /**
     * The relay a call to the partner goes through, as the web asks for before calling or answering.
     */
    public function index(ListApiConversationCallIceServersRequest $request, User $partner, TurnProvider $turn): JsonResponse
    {
        return response()->json([
            'ice_servers' => $turn->iceServers(),
            'is_relay_only' => $turn->isRelayOnly(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
