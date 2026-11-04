<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Sent once the transaction commits, on the public features channel, when a feature was switched on or off for
 * everyone, so what pages offer follows without a reload. It carries nothing.
 */
final class FeaturesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('features')];
    }

    public function shouldBroadcastNow(): bool
    {
        return true;
    }

    public function broadcastAs(): string
    {
        return 'features';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
