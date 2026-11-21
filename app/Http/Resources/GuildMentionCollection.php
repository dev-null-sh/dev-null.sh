<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

final class GuildMentionCollection extends AnonymousResourceCollection
{
    /** @var Collection<array-key, CommitResource|MessageResource|ScheduledCommitResource|NotificationResource|ConversationResource> */
    public $collection;

    private ?GuildBadges $guildBadges = null;

    /**
     * @param  class-string<CommitResource|MessageResource|ScheduledCommitResource|NotificationResource|ConversationResource>  $collects
     * @param  Closure(iterable<array-key, mixed>, ?User): GuildBadges  $lookUp
     */
    public function __construct(mixed $resource, string $collects, private readonly Closure $lookUp)
    {
        parent::__construct($resource, $collects);
    }

    public function withGuildBadges(GuildBadges $badges): self
    {
        $collection = clone $this;
        $collection->guildBadges = $badges;

        return $collection;
    }

    /**
     * @return array<array-key, array<mixed>>
     */
    public function toArray(Request $request): array
    {
        $badges = $this->guildBadges ?? ($this->lookUp)($this->collection->pluck('resource'), $request->user());

        return $this->collection->map(fn (CommitResource|MessageResource|ScheduledCommitResource|NotificationResource|ConversationResource $resource): array => $resource->withGuildBadges($badges)->resolve($request))->all();
    }
}
