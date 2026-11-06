<?php

declare(strict_types=1);

use App\Enums\ActiveDayApp;
use App\Models\Device;
use Illuminate\Http\Request;

function apiRequest(?string $appId = null, ?string $userAgent = null): Request
{
    $request = Request::create('/api/v1/me', server: $userAgent === null ? [] : ['HTTP_USER_AGENT' => $userAgent]);

    if ($appId !== null) {
        $request->attributes->set(Device::class, new Device(['app_id' => $appId]));
    }

    return $request;
}

it('counts the web routes as web, whatever the client says', function (): void {
    $request = Request::create('/logs/following', server: ['HTTP_USER_AGENT' => 'DevNull-Mac/1.0']);

    expect(ActiveDayApp::forRequest($request))->toBe(ActiveDayApp::Web);
});

it('counts the web routes as the lite mobile app when its web view says so', function (): void {
    $request = Request::create('/logs/following', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 DevNullLite/1.0.0']);

    expect(ActiveDayApp::forRequest($request))->toBe(ActiveDayApp::LiteMobile);
});

it('tells the app of an api request', function (Request $request, ActiveDayApp $app): void {
    expect(ActiveDayApp::forRequest($request))->toBe($app);
})->with([
    'iOS app' => fn (): array => [apiRequest('ios:com.nativephp.devnull'), ActiveDayApp::NativeMobile],
    'Android app' => fn (): array => [apiRequest('android:com.nativephp.devnull'), ActiveDayApp::NativeMobile],
    'lite mobile app' => fn (): array => [apiRequest('ios:sh.devnull.app'), ActiveDayApp::LiteMobile],
    'another client saying it is the lite mobile app, as only web views say it' => fn (): array => [apiRequest(userAgent: 'DevNullLite/1.0.0'), ActiveDayApp::Other],
    'Mac app' => fn (): array => [apiRequest(userAgent: 'DevNull-Mac/1.0'), ActiveDayApp::Mac],
    'Mac app with its id' => fn (): array => [apiRequest('macos:sh.dev-null.mac', 'DevNull-Mac/1.0'), ActiveDayApp::Mac],
    'phone approved before the app sent its id' => fn (): array => [apiRequest(userAgent: 'GuzzleHttp/7'), ActiveDayApp::Other],
    'development build of the app' => fn (): array => [apiRequest('ios:com.simonhamp.devnull'), ActiveDayApp::Other],
    'terminal app or commit script' => fn (): array => [apiRequest(userAgent: 'curl/8.7.1'), ActiveDayApp::Other],
]);
