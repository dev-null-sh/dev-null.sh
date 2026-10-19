<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The code that comes with an error from the signed API, next to its message, so the mobile app can tell errors apart
 * without reading the message. Once released, a code never changes.
 */
enum ApiErrorCode: string
{
    case InvalidSignature = 'invalid_signature';
    case DeviceNotApproved = 'device_not_approved';
    case InvalidGrant = 'invalid_grant';
    case UserBlocked = 'user_blocked';
    case UserUnavailable = 'user_unavailable';
    case InvalidAppleToken = 'invalid_apple_token';
    case AppleLinkedElsewhere = 'apple_linked_elsewhere';
    case AppleNotLinked = 'apple_not_linked';
    case RateLimited = 'rate_limited';

    public function status(): int
    {
        return match ($this) {
            self::InvalidGrant => 400,
            self::InvalidSignature, self::DeviceNotApproved => 401,
            self::UserBlocked, self::UserUnavailable => 403,
            self::AppleNotLinked => 404,
            self::AppleLinkedElsewhere => 409,
            self::InvalidAppleToken => 422,
            self::RateLimited => 429,
        };
    }
}
