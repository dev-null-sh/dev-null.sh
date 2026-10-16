<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\AuthorizationCode;
use App\Models\Device;
use Illuminate\Support\Facades\Cache;

final readonly class CreateDevice
{
    /**
     * Redeems an authorization code: the key it was handed out for becomes an approved device of the user who approved
     * it, and the code is used up. A key that was approved before, even for someone else, is approved again for them.
     *
     * A code is redeemed once, like a signature nonce is used once, so of two requests with the same code only one gets
     * a device. The other gets null.
     */
    public function handle(AuthorizationCode $authorizationCode): ?Device
    {
        if (! Cache::add("{$authorizationCode->key}:redeemed", true, now()->addMinutes(AuthorizationCode::EXPIRES_AFTER_MINUTES))) {
            return null;
        }

        Cache::forget($authorizationCode->key);

        return Device::query()->updateOrCreate(['fingerprint' => $authorizationCode->fingerprint], [
            'user_id' => $authorizationCode->userId,
            'name' => $authorizationCode->deviceName,
            'approved_at' => now(),
            'last_used_at' => null,
        ]);
    }
}
