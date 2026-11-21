<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\CommitBodies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A commit the user queued, as the web's Scheduled page shows it. Once submitted, its body lives on the commit and
 * only the status says what became of it: scheduled, the commit's moderation status, or deleted. Its author is the
 * organization it goes out as, or null when it goes out as the user.
 *
 * @mixin ScheduledCommit
 */
final class ScheduledCommitResource extends JsonResource
{
    private ?GuildBadges $guildBadges = null;

    public static function newCollection(mixed $resource): GuildMentionCollection
    {
        return new GuildMentionCollection($resource, self::class, self::guildBadges(...));
    }

    /**
     * @param  iterable<array-key, mixed>  $scheduledCommits
     */
    public static function guildBadges(iterable $scheduledCommits, ?User $viewer): GuildBadges
    {
        return GuildBadges::forTexts(collect($scheduledCommits)
            ->filter(fn (mixed $scheduledCommit): bool => $scheduledCommit instanceof ScheduledCommit && $scheduledCommit->submitted_at === null && $scheduledCommit->body !== null)
            ->map(fn (ScheduledCommit $scheduledCommit): string => (string) $scheduledCommit->body), $viewer);
    }

    public function withGuildBadges(GuildBadges $badges): self
    {
        $resource = clone $this;
        $resource->guildBadges = $badges;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $badges = $this->guildBadges ?? self::guildBadges([$this->resource], $request->user());

        return [
            'id' => $this->id,
            'author' => $this->author === null ? null : ProfileResource::make($this->author)->resolve($request),
            ...($this->submitted_at === null && $this->body !== null
                ? ['body' => $this->body, ...resolve(CommitBodies::class)->segments($this->body), 'guild_mentions' => $badges->in($this->body)]
                : ['body' => null, 'body_segments' => [], 'preview_segments' => null]),
            'poll_options' => $this->poll['options'] ?? null,
            'image_count' => count($this->images ?? []),
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'status' => $this->submitted_at === null ? 'scheduled' : ($this->commit === null || $this->commit->trashed() ? 'deleted' : $this->commit->status->value),
        ];
    }
}
