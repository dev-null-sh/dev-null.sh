<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\TurnProvider;
use App\Http\Requests\ListApiOrgCallIceServersRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

final readonly class OrgCallIceServerController
{
    /**
     * The relay the org's call goes through, as the web asks for before starting or joining it.
     */
    public function index(ListApiOrgCallIceServersRequest $request, Conversation $org, TurnProvider $turn): JsonResponse
    {
        return response()->json([
            'ice_servers' => $turn->iceServers(),
            'is_relay_only' => $turn->isRelayOnly(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
