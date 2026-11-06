<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Sent once the transaction commits, on the public headlines channel, when commits joined a headline, so the hot
 * topics everyone is shown may have changed. It carries nothing: each reader's hot topics are read again for them.
 */
final class HeadlinesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('headlines')];
    }

    public function shouldBroadcastNow(): bool
    {
        return true;
    }

    public function broadcastAs(): string
    {
        return 'headlines';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
