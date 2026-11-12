<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\TurnProvider;
use App\Http\Requests\ListOrgCallIceServersRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

final readonly class OrgCallIceServerController
{
    public function index(ListOrgCallIceServersRequest $request, Conversation $org, TurnProvider $turn): JsonResponse
    {
        return response()->json([
            'ice_servers' => $turn->iceServers(),
            'is_relay_only' => $turn->isRelayOnly(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
