<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteDevice;
use App\Enums\UserUpdate;
use App\Http\Requests\DeleteDeviceRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DeviceController
{
    /**
     * The devices that may use the signed API through the devnull app, each approved on the web from the app.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('device/index', [
            // A device approved or revoked elsewhere shows here as it happens.
            'devices' => Inertia::live(fn (): Collection => $user->devices()->latest('approved_at')->get(), UserUpdate::Account->value, new PrivateChannel($user)),
        ]);
    }

    public function destroy(DeleteDeviceRequest $request, Device $device, DeleteDevice $action): RedirectResponse
    {
        $action->handle($device);

        return back();
    }
}
