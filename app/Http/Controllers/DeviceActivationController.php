<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateDeviceActivation;
use App\Dto\DeviceAuthorization;
use App\Http\Requests\CreateDeviceActivationRequest;
use App\Http\Requests\ShowDeviceActivationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DeviceActivationController
{
    /**
     * Asks the user for the code their terminal shows and, once it is found, to approve or deny the device behind
     * it, with its name and the key's fingerprint. The forms are plain posts, as on the mobile page, so a code that
     * can't be found comes back as this page with the error, never as a redirect.
     */
    public function show(ShowDeviceActivationRequest $request): Response
    {
        $authorization = $request->authorization();

        return Inertia::render('device-activation/show', $authorization instanceof DeviceAuthorization ? [
            'authorization' => [
                'user_code' => $authorization->userCode,
                'device_name' => $authorization->deviceName,
                'fingerprint' => $authorization->fingerprint,
            ],
            'csrfToken' => csrf_token(),
        ] : []);
    }

    /**
     * Approves the key as one of the user's devices, or denies it, and tells the user what their terminal will find.
     */
    public function store(CreateDeviceActivationRequest $request, #[CurrentUser] User $user, CreateDeviceActivation $action): Response
    {
        $authorization = $request->authorization();
        $decision = $request->decision();

        $action->handle($user, $authorization, $decision);

        return Inertia::render('device-activation/show', [
            'done' => [
                'status' => $decision->value,
                'device_name' => $authorization->deviceName,
            ],
        ]);
    }
}
