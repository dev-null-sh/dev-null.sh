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
     * Fresh relay credentials for one call with the partner, which the app's WebRTC connection goes through as the
     * browser's does.
     */
    public function index(ListApiConversationCallIceServersRequest $request, User $partner, TurnProvider $turn): JsonResponse
    {
        return response()->json([
            'ice_servers' => $turn->iceServers(),
            'is_relay_only' => $turn->isRelayOnly(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
