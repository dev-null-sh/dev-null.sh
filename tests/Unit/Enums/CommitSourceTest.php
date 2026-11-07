<?php

declare(strict_types=1);

use App\Dto\MobileAuthorization;
use App\Enums\ActiveDayApp;
use App\Enums\CommitSource;
use App\Models\Device;
use Illuminate\Http\Request;

it('uses the same app identification as daily usage for commit sources', function (string $path, ?string $appId, ?string $agent, ActiveDayApp $app, CommitSource $source): void {
    $request = Request::create($path, server: $agent === null ? [] : ['HTTP_USER_AGENT' => $agent]);

    if ($appId !== null) {
        $request->attributes->set(Device::class, new Device(['app_id' => $appId]));
    }

    expect(ActiveDayApp::forRequest($request))->toBe($app)
        ->and(CommitSource::forRequest($request))->toBe($source);
})->with([
    'web browser' => ['/log/latest', null, 'Mozilla/5.0 (iPhone)', ActiveDayApp::Web, CommitSource::Web],
    'mac user-agent on a web route is still web' => ['/log/latest', null, 'DevNull-Mac/1.0', ActiveDayApp::Web, CommitSource::Web],
    'lite web view' => ['/log/latest', null, 'Mozilla/5.0 DevNullLite/1.0', ActiveDayApp::LiteMobile, CommitSource::LiteMobile],
    'native iOS app' => ['/api/v1/me', 'ios:com.nativephp.devnull', null, ActiveDayApp::NativeMobile, CommitSource::Ios],
    'native Android app' => ['/api/v1/me', 'android:com.nativephp.devnull', null, ActiveDayApp::NativeMobile, CommitSource::Android],
    'lite signed app' => ['/api/v1/me', MobileAuthorization::LiteMobileAppId, null, ActiveDayApp::LiteMobile, CommitSource::LiteMobile],
    'mac desktop user-agent' => ['/api/v1/me', null, 'DevNull-Mac/1.0', ActiveDayApp::Mac, CommitSource::Macos],
    'mac app id alone without user-agent' => ['/api/v1/me', 'macos:sh.dev-null.mac', null, ActiveDayApp::Other, CommitSource::Api],
    'unrecognized iOS app' => ['/api/v1/me', 'ios:sh.dev-null.app', null, ActiveDayApp::Other, CommitSource::Api],
    'older device without an app id' => ['/api/v1/me', null, 'curl/8', ActiveDayApp::Other, CommitSource::Api],
    'spoofed lite user-agent on API' => ['/api/v1/me', null, 'DevNullLite/1.0', ActiveDayApp::Other, CommitSource::Api],
    'other app' => ['/api/v1/me', 'windows:sh.dev-null.app', null, ActiveDayApp::Other, CommitSource::Api],
]);
