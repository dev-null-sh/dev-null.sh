<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\CommitUpdate;
use App\Models\Commit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Sent once the transaction commits, on each public commit's channel. Whoever shows the commit hears about it, and
 * when it appears or disappears so do the threads it is a reply or quote in, and the logs. Callers only pass commits
 * everyone could see before or after the change, and only their ids are sent.
 */
final class CommitsUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    /**
     * @var list<string>
     */
    public array $commitIds;

    /**
     * @var list<string>
     */
    public array $channelIds;

    /**
     * @param  iterable<int, Commit>  $commits
     */
    public function __construct(iterable $commits, public CommitUpdate $update)
    {
        $commits = collect($commits);

        $this->commitIds = array_values(array_unique($commits->map(fn (Commit $commit): string => $commit->id)->all()));
        $this->channelIds = $update === CommitUpdate::Visibility
            ? array_values(array_unique(array_filter($commits->flatMap(fn (Commit $commit): array => [$commit->id, $commit->parent_id, $commit->quoted_commit_id])->all(), is_string(...))))
            : $this->commitIds;
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            ...array_map(fn (string $id): Channel => new Channel('commits.'.$id), $this->channelIds),
            ...$this->update === CommitUpdate::Visibility ? [new Channel('log')] : [],
        ];
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
     * @return array{ids: list<string>}
     */
    public function broadcastWith(): array
    {
        return ['ids' => $this->commitIds];
    }

    public function broadcastWhen(): bool
    {
        return $this->commitIds !== [];
    }
}
