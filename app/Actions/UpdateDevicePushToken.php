<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PushPlatform;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

final readonly class UpdateDevicePushToken
{
    /**
     * Pushes for the device go to this token from now on. A token belongs to the phone that sent it last, so it leaves
     * any other device that had it, such as the one the phone signed in as before, under this account or another.
     */
    public function handle(Device $device, string $token, PushPlatform $platform): void
    {
        $hash = hash('sha256', $token);

        DB::transaction(function () use ($device, $token, $platform, $hash): void {
            Device::query()->where('push_token_hash', $hash)->whereKeyNot($device->id)
                ->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);

            $device->update(['push_token_hash' => $hash, 'push_token' => $token, 'push_platform' => $platform]);
        });
    }
}
