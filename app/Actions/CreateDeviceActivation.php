<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class CreateDeviceActivation
{
    /**
     * Decides a sign-in started from a terminal. Approving makes its key a device of the user right away, as redeeming
     * a code does for the mobile app, since the key proved itself when it started the sign-in and the user approved
     * that very fingerprint. Either way, the decision is kept for the terminal to find until the sign-in would have
     * expired, and the user code is taken back, so a code is decided once.
     */
    public function handle(User $user, DeviceAuthorization $authorization, DeviceAuthorizationStatus $status): void
    {
        if ($status === DeviceAuthorizationStatus::Approved) {
            Device::query()->updateOrCreate(['fingerprint' => $authorization->fingerprint], [
                'user_id' => $user->id,
                'name' => $authorization->deviceName,
                'approved_at' => now(),
                'last_used_at' => null,
            ]);
        }

        Cache::put($authorization->key, $authorization->decided($status, $user)->toArray(), $authorization->expiresAt);
        Cache::forget(DeviceAuthorization::userCodeKeyFor($authorization->userCode));
    }
}
