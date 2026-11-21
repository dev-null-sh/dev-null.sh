<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Guild;
use App\Models\Poll;
use App\Models\User;
use App\Services\CommitBodies;
use App\Services\Mentions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A commit and its author, a user or an organization as its type says, as anyone may see them.
 *
 * A signed request gets the commit as the web shows it to that user instead: parent_id is the parent the commit
 * replies to, whoever may see it, and on top come the body split into prose, inline code and highlighted code
 * blocks, where it mentions someone, its status, whether the author pinned it, whether the user starred,
 * and how many pastéis it was given, which the app only shows in pastéis mode, as the web does. Like the web's cards,
 * it also has preview_segments, the excerpt a list shows of a long commit, the poll, with what the user may see of
 * its votes, and the commit it quotes, or null once the user may no longer see that one, while quoted_commit_id still
 * says it quoted something. In a list, reply_preview is the newest reply or quote the web shows under it. As on the
 * web, the counts, link_preview, images, starred, parent, poll, quoted_commit and reply_preview are only
 * there when they were loaded, so ancestors and the commits of notifications come without some of them. The images of
 * a commit that isn't published yet have temporary URLs, since the app has no web session to load them with. For the
 * same reason, the guild's icon_url is signed for the user, or a guest, and stays the same all day.
 *
 * A deleted commit that still has replies stays in its thread as a tombstone: its id, parent_id, deleted_at and
 * replies_count, but never its author or body. Only signed requests are ever sent one.
 *
 * @mixin Commit
 */
final class CommitResource extends JsonResource
{
    private ?GuildBadges $guildBadges = null;

    public static function newCollection(mixed $resource): GuildMentionCollection
    {
        return new GuildMentionCollection($resource, self::class, self::guildBadges(...));
    }

    /**
     * @param  iterable<array-key, mixed>  $commits
     */
    public static function guildBadges(iterable $commits, ?User $viewer): GuildBadges
    {
        return GuildBadges::forCommits(collect($commits)->map(fn (mixed $commit): ?Commit => $commit instanceof Commit ? $commit : null), $viewer);
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
        if ($this->trashed()) {
            return [
                'id' => $this->id,
                'parent_id' => $this->parent_id,
                'deleted_at' => $this->deleted_at?->toIso8601ZuluString(),
                'replies_count' => $this->whenCounted('replies'),
            ];
        }

        $isSigned = $request->user() instanceof User;

        $commit = [
            'id' => $this->id,
            'short_hash' => mb_substr(str_replace('-', '', $this->id), -7),
            'body' => $this->body,
            'guild_handle' => $this->whenLoaded('guild', fn (Guild $guild): string => $guild->handle),
            'guild' => $this->whenLoaded('guild', fn (Guild $guild): array => [
                'handle' => $guild->handle,
                'name' => $guild->name,
                'emoji' => $guild->emoji,
                'icon_url' => $guild->apiIconUrl($request->user()),
            ]),
            'parent_id' => $isSigned ? $this->parent_id : $this->parent?->id,
            'html_url' => route('commit.show', [$this->author, $this->resource]),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'stars_count' => $this->whenCounted('stars'),
            'replies_count' => $this->whenCounted('replies'),
            'link_preview' => $this->whenLoaded('linkPreview'),
            'images' => $this->whenLoaded('images', fn (): mixed => $this->status === CommitStatus::Approved || ! $this->imagesAreVisibleTo($request->user())
                ? $this->images
                : $this->images->map(fn (CommitImage $image): array => [...$image->toArray(), 'url' => $image->temporaryUrl()])),
            'author' => ProfileResource::make($this->author),
        ];

        if (! $isSigned) {
            return $commit;
        }

        $badges = $this->guildBadges ?? self::guildBadges([$this->resource], $request->user());

        return [
            ...$commit,
            ...resolve(CommitBodies::class)->segments($this->body),
            'mentions' => resolve(Mentions::class)->positions($this->body, $this->organization_mentions ?? []),
            'status' => $this->status,
            'is_pinned' => $this->author?->user?->pinned_commit_id === $this->id,
            'starred' => $this->whenHas('starred'),
            'pasteis_count' => $this->pasteis_count,
            'deleted_at' => null,
            'parent' => $this->whenLoaded('parent', fn (Commit $parent): self => self::make($parent)->withGuildBadges($badges)),
            'quoted_commit_id' => $this->quoted_commit_id,
            'quoted_commit' => $this->whenLoaded('quotedCommit', fn (Commit $quoted): self => self::make($quoted)->withGuildBadges($badges)),
            'poll' => $this->whenLoaded('poll', fn (Poll $poll): array => $badges->onPoll(PollResource::make($poll)->resolve($request))),
            'reply_preview' => $this->whenLoaded('replyPreview', fn (Collection $replies): array => [
                'data' => self::newCollection($replies)->withGuildBadges($badges),
                'next_cursor' => data_get($this->getAttribute('reply_preview'), 'next_cursor'),
            ]),
            'guild_mentions' => $badges->in($this->body),
        ];
    }
}
