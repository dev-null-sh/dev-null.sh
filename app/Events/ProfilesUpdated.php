<?php

declare(strict_types=1);

namespace App\Events;

use App\Dto\LiveStream;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Sent once the transaction commits, on each public profile's channel, when what its page shows about it changed:
 * its counts, its pinned commit, or its live stream. Nothing is sent but a stream that started or ended, which its
 * profile shows everyone anyway, so every card showing the person can mark them live or not without asking.
 */
final class ProfilesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    /**
     * @var list<string>
     */
    public array $profileIds;

    /**
     * @param  iterable<array-key, mixed>  $profileIds
     */
    public function __construct(iterable $profileIds, public bool $changesLive = false, public ?LiveStream $live = null, public ?CarbonInterface $liveEndsAt = null, public bool $changesConnections = false)
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
     * Who follows whom stays for members, so only that it changed is said. A stream goes with when it runs out, so a
     * page left open stops showing it then.
     *
     * @return array{live?: array{url: string, thumbnail_url: string, ends_at: string|null}|null, connections?: true}
     */
    public function broadcastWith(): array
    {
        return [
            ...$this->changesLive ? ['live' => $this->live instanceof LiveStream ? [...$this->live->toArray(), 'ends_at' => $this->liveEndsAt?->toJSON()] : null] : [],
            ...$this->changesConnections ? ['connections' => true] : [],
        ];
    }

    public function broadcastWhen(): bool
    {
        return $this->profileIds !== [];
    }
}
