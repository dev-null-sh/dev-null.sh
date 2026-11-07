<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Where a commit was created. It reuses the app classification that also
 * counts daily app usage, then distinguishes supported mobile platforms.
 */
enum CommitSource: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    case LiteMobile = 'lite_mobile';
    case Macos = 'macos';
    case Cli = 'cli';
    case Api = 'api';

    /** The request determines the source; a commit payload cannot choose it. */
    public static function forRequest(Request $request): self
    {
        $device = $request->attributes->get(Device::class);
        $appId = $device instanceof Device ? $device->app_id : null;

        return match (ActiveDayApp::forRequest($request)) {
            ActiveDayApp::Web => self::Web,
            ActiveDayApp::LiteMobile => self::LiteMobile,
            ActiveDayApp::Mac => self::Macos,
            ActiveDayApp::Other => self::Api,
            ActiveDayApp::NativeMobile => match (explode(':', (string) $appId, 2)[0]) {
                'ios' => self::Ios,
                'android' => self::Android,
                default => self::Api,
            },
        };
    }
}
