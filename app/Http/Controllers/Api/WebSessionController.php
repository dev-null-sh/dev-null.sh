<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateApiWebSessionRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final readonly class WebSessionController
{
    /**
     * Signs in the web session whose cookies this response sets, which the app hands to its web view. Only the app can
     * ask for one and only the app reads the answer, so there is nothing a browser could be sent to, and it needs no
     * remember cookie: the app makes a new session when this one ends.
     */
    public function store(CreateApiWebSessionRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->put(Device::SessionKey, $request->device()->id);

        return response()->json(['onboarded' => $user->isOnboarded()]);
    }
}
