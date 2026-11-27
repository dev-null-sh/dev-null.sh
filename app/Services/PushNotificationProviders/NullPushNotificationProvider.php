<?php

declare(strict_types=1);

namespace App\Services\PushNotificationProviders;

use App\Contracts\Services\PushNotificationProvider;
use App\Models\PushSubscription;

/**
 * Pushes nothing, so developing and testing never reach a browser's push service.
 */
final readonly class NullPushNotificationProvider implements PushNotificationProvider
{
    public function publicKey(): ?string
    {
        return null;
    }

    public function send(PushSubscription $subscription): bool
    {
        return true;
    }
}
