<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Finds the sign-ins started from terminals, which only live in the cache.
 */
final readonly class DeviceAuthorizations
{
    /**
     * The sign-in the user is asked to decide on, by the code they typed, while nothing is decided yet: a decided
     * code is taken back the moment it is decided, so it can't be decided twice.
     */
    public function pendingFromUserCode(string $userCode): ?DeviceAuthorization
    {
        $key = Cache::get(DeviceAuthorization::userCodeKeyFor($userCode));
        $authorization = is_string($key) ? $this->fromKey($key) : null;

        return $authorization?->status === DeviceAuthorizationStatus::Pending ? $authorization : null;
    }

    private function fromKey(string $key): ?DeviceAuthorization
    {
        $stored = Cache::get($key);

        return is_array($stored) ? DeviceAuthorization::fromArray($key, $stored) : null;
    }
}
