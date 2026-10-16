<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Device;

final readonly class UpdateOrCreateDevice
{
    /**
     * Approves the key as a device of the user. A key that was approved before, even for someone else, is approved
     * again for them.
     */
    public function handle(string $userId, string $fingerprint, string $name): Device
    {
        return Device::query()->updateOrCreate(['fingerprint' => $fingerprint], [
            'user_id' => $userId,
            'name' => $name,
            'approved_at' => now(),
            'last_used_at' => null,
        ]);
    }
}
