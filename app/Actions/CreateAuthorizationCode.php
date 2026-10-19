<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\AuthorizationCode;
use App\Dto\MobileAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class CreateAuthorizationCode
{
    /**
     * Hands out a one-time code for the devnull app to redeem for a device, bound to what the user approved. It is only
     * kept in the cache, under its hash, until it expires a few minutes later.
     */
    public function handle(User $user, MobileAuthorization $authorization): string
    {
        $code = bin2hex(random_bytes(32));

        $authorizationCode = new AuthorizationCode(
            AuthorizationCode::keyFor($code),
            $user->id,
            $authorization->fingerprint,
            $authorization->deviceName,
            $authorization->codeChallenge,
        );

        Cache::put($authorizationCode->key, $authorizationCode->toArray(), now()->addMinutes(AuthorizationCode::EXPIRES_AFTER_MINUTES));

        return $code;
    }
}
