<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent once the transaction commits, on each public profile's channel, when what its page shows about it changed:
 * its counts, its pinned commit, or its live stream. Only the ids are sent.
 */
final class ProfilesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    /**
     * @var list<string>
     */
    public array $profileIds;

    /**
     * @param  iterable<array-key, mixed>  $profileIds
     */
    public function __construct(iterable $profileIds)
    {
        $this->profileIds = array_values(array_unique(array_filter(collect($profileIds)->all(), is_string(...))));
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id): Channel => new Channel('profiles.'.$id), $this->profileIds);
    }

    /**
     * One request to the broadcaster carries up to a hundred channels, so that much is sent right away, and a wider
     * audience is left to the queue instead of holding up the request.
     */
    public function shouldBroadcastNow(): bool
    {
        return count($this->broadcastOn()) <= 100;
    }

    public function broadcastAs(): string
    {
        return 'profile';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }

    public function broadcastWhen(): bool
    {
        return $this->profileIds !== [];
    }
}
