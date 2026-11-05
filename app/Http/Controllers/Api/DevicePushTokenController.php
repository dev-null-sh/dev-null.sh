<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteDevicePushToken;
use App\Actions\UpdateDevicePushToken;
use App\Http\Requests\DeleteApiDevicePushTokenRequest;
use App\Http\Requests\UpdateApiDevicePushTokenRequest;
use Illuminate\Http\Response;

final readonly class DevicePushTokenController
{
    /**
     * Saves the token pushes reach the phone that signed the request with, replacing the one it sent before.
     */
    public function update(UpdateApiDevicePushTokenRequest $request, UpdateDevicePushToken $action): Response
    {
        $action->handle($request->device(), $request->string('token')->toString(), $request->platform());

        return response()->noContent();
    }

    /**
     * Stops pushing to the phone that signed the request, as when the app signs out or notifications are turned off.
     */
    public function destroy(DeleteApiDevicePushTokenRequest $request, DeleteDevicePushToken $action): Response
    {
        $action->handle($request->device());

        return response()->noContent();
    }
}
