<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateDeviceAuthorization;
use App\Dto\DeviceAuthorization;
use App\Http\Requests\CreateApiDeviceAuthorizationRequest;
use Illuminate\Http\JsonResponse;

final readonly class DeviceAuthorizationController
{
    /**
     * Starts a sign-in from a terminal, the device authorization grant of RFC 8628. The terminal shows the user the
     * code and where to type it, then polls POST /api/v1/devices with the device code, no more often than the interval
     * says, until the user decides on the web.
     */
    public function store(CreateApiDeviceAuthorizationRequest $request, CreateDeviceAuthorization $action): JsonResponse
    {
        ['device_code' => $deviceCode, 'authorization' => $authorization] = $action->handle($request->fingerprint(), $request->string('device_name')->value());

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $authorization->userCode,
            'verification_uri' => route('device-activation.show'),
            'verification_uri_complete' => route('device-activation.show', ['user_code' => $authorization->userCode]),
            'expires_in' => DeviceAuthorization::EXPIRES_AFTER_MINUTES * 60,
            'interval' => DeviceAuthorization::INTERVAL_SECONDS,
        ], 201);
    }
}
