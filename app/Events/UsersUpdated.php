<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\UserUpdate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Sent once the transaction that changed something commits, so a rolled back change is never announced, and a
 * broadcaster that is down never fails the request that changed it. Nothing is delivered twice or kept for later:
 * whoever missed it catches up by reading again once they listen.
 */
final class UsersUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use InteractsWithSockets;

    /**
     * @var list<string>
     */
    public array $userIds;

    /**
     * @param  iterable<array-key, mixed>  $userIds
     * @param  array<string, int|string|list<string>|null>  $data
     */
    public function __construct(iterable $userIds, public UserUpdate $update, public array $data = [])
    {
        $this->userIds = array_values(array_unique(array_filter(collect($userIds)->all(), is_string(...))));
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id): PrivateChannel => new PrivateChannel('users.'.$id), $this->userIds);
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
        return $this->update->value;
    }

    /**
     * @return array<string, int|string|list<string>|null>
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastWhen(): bool
    {
        return $this->userIds !== [];
    }
}
