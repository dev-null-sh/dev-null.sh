<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\PushSubscription;

/**
 * Wakes a subscribed browser so it fetches what's new itself: the push carries no message data.
 */
interface PushNotificationProvider
{
    /**
     * The key browsers subscribe with, so only this server can push to them. Null when nothing can be pushed.
     */
    public function publicKey(): ?string;

    /**
     * Pushes to the browser, and forgets the subscription once the browser no longer has it. False when it should be
     * tried again later.
     */
    public function send(PushSubscription $subscription): bool;
}
