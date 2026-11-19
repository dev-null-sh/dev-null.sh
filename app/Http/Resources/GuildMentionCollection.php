<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

final class GuildMentionCollection extends AnonymousResourceCollection
{
    /** @var Collection<array-key, MessageResource|ScheduledCommitResource|ConversationResource|CommitResource|NotificationResource> */
    public $collection;

    public bool $withGuildMentions = true;

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shown = $this->collection->map(function (MessageResource|ScheduledCommitResource|ConversationResource|CommitResource|NotificationResource $resource) use ($request): array {
            $resource = clone $resource;
            $resource->withGuildMentions = false;

            return $resource->resolve($request);
        })->all();

        return $this->withGuildMentions ? ShownGuildMentions::of($shown, $request->user()) : $shown;
    }
}
