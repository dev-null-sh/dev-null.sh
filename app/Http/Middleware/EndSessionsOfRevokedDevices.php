<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EndSessionsOfRevokedDevices
{
    /**
     * A web session made for a device lasts as long as the device stays this user's: revoking it signs out its web view
     * too. A key approved for someone else keeps its device's id, hence the check on the user. Only this session ends:
     * logging out would also end the remember cookie of the browser the device was revoked from.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->session()->get(Device::SessionKey);

        if ($deviceId !== null && ! Device::query()->whereKey($deviceId)->where('user_id', $request->user()?->getAuthIdentifier())->exists()) {
            Auth::guard('web')->logoutCurrentDevice();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
