<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\DeviceAuthorization;
use App\Services\UserCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class CreateDeviceAuthorization
{
    public function __construct(private UserCodes $userCodes)
    {
        //
    }

    /**
     * Starts a sign-in from a terminal for a key: a long device code for the terminal to poll with, and a short user
     * code for the user to type on the web, where approving it makes the key a device. Both only live in the cache,
     * under their hashes, until the sign-in expires a few minutes later. A user code that is already out, however
     * unlikely, is drawn again rather than pointed at two sign-ins.
     *
     * @return array{device_code: string, authorization: DeviceAuthorization}
     */
    public function handle(string $fingerprint, string $deviceName): array
    {
        $deviceCode = bin2hex(random_bytes(32));
        $key = DeviceAuthorization::keyFor($deviceCode);
        $expiresAt = CarbonImmutable::now()->addMinutes(DeviceAuthorization::EXPIRES_AFTER_MINUTES)->startOfSecond();

        do {
            $userCode = $this->userCodes->generate();
        } while (! Cache::add(DeviceAuthorization::userCodeKeyFor($userCode), $key, $expiresAt));

        $authorization = new DeviceAuthorization($key, $fingerprint, $deviceName, $userCode, $expiresAt);

        Cache::put($key, $authorization->toArray(), $expiresAt);

        return ['device_code' => $deviceCode, 'authorization' => $authorization];
    }
}
