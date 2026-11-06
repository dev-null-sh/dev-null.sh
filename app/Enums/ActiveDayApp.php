<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * The app someone was seen in, each case the column of user_active_days that records it. Only metrics read it: a client
 * says what it is about itself, so it never authorizes anything.
 */
enum ActiveDayApp: string
{
    case Web = 'web_app';
    case NativeMobile = 'native_mobile_app';
    case LiteMobile = 'lite_mobile_app';
    case Mac = 'mac_app';
    case Other = 'other_app';

    private const string NativeMobileBundleId = 'com.nativephp.devnull';

    private const string LiteMobileAppId = 'ios:sh.devnull.app';

    /**
     * Browsers come through the web routes, and everything else through the API. The lite mobile app is both: its web
     * view says so in its user agent, and its own requests in the app id of its device. The NativePHP app also says so in
     * the app id of its device, on iOS and Android alike, and the Mac app in its user agent. Phones approved before the
     * app sent an id have none and count as other, along with the terminal app, the commit script and the GitHub Action.
     */
    public static function forRequest(Request $request): self
    {
        if (! $request->is('api/*')) {
            return str_contains((string) $request->userAgent(), 'DevNullLite/') ? self::LiteMobile : self::Web;
        }

        $device = $request->attributes->get(Device::class);

        return match (true) {
            $device instanceof Device && $device->app_id === self::LiteMobileAppId => self::LiteMobile,
            $device instanceof Device && str_ends_with((string) $device->app_id, ':'.self::NativeMobileBundleId) => self::NativeMobile,
            str_starts_with((string) $request->userAgent(), 'DevNull-Mac') => self::Mac,
            default => self::Other,
        };
    }
}
