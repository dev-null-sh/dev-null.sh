<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PushPlatform;
use App\Models\Device;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UpdateDevicePushToken
{
    /**
     * Pushes for the device go to this token from now on. A token belongs to the phone that sent it last, so it leaves
     * any other device that had it, such as the one the phone signed in as before, under this account or another.
     * Two phones sending the same token at once can both clear it before either takes it, so the one that loses the
     * unique index starts the move again. The device is read again under a lock, since the copy authentication loaded
     * can be out of date, and Eloquent skips writing a value it thinks is unchanged.
     */
    public function handle(Device $device, string $token, PushPlatform $platform): void
    {
        $hash = hash('sha256', $token);

        retry(3, fn () => DB::transaction(function () use ($device, $token, $platform, $hash): void {
            Device::query()->where('push_token_hash', $hash)->whereKeyNot($device->id)
                ->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);

            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail()
                ->update(['push_token_hash' => $hash, 'push_token' => $token, 'push_platform' => $platform]);
        }), when: fn (Throwable $exception): bool => $exception instanceof UniqueConstraintViolationException);
    }
}
