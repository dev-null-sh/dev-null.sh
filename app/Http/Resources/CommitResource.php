<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Poll;
use App\Models\User;
use App\Services\CommitBodies;
use App\Services\Mentions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A commit and its author, as anyone may see them.
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
 * a commit that isn't published yet have temporary URLs, since the app has no web session to load them with.
 *
 * A deleted commit that still has replies stays in its thread as a tombstone: its id, parent_id, deleted_at and
 * replies_count, but never its author or body. Only signed requests are ever sent one.
 *
 * @mixin Commit
 */
final class CommitResource extends JsonResource
{
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
            'parent_id' => $isSigned ? $this->parent_id : $this->parent?->id,
            'html_url' => route('commit.show', [$this->user, $this->resource]),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'stars_count' => $this->whenCounted('stars'),
            'replies_count' => $this->whenCounted('replies'),
            'link_preview' => $this->whenLoaded('linkPreview'),
            'images' => $this->whenLoaded('images', fn (): mixed => $this->status === CommitStatus::Approved || ! $this->imagesAreVisibleTo($request->user())
                ? $this->images
                : $this->images->map(fn (CommitImage $image): array => [...$image->toArray(), 'url' => $image->temporaryUrl()])),
            'author' => UserResource::make($this->user),
        ];

        if (! $isSigned) {
            return $commit;
        }

        return [
            ...$commit,
            ...resolve(CommitBodies::class)->segments($this->body),
            'mentions' => resolve(Mentions::class)->positions($this->body),
            'status' => $this->status,
            'is_pinned' => $this->user?->pinned_commit_id === $this->id,
            'starred' => $this->whenHas('starred'),
            'pasteis_count' => $this->pasteis_count,
            'deleted_at' => null,
            'parent' => $this->whenLoaded('parent', fn (Commit $parent): self => self::make($parent)),
            'quoted_commit_id' => $this->quoted_commit_id,
            'quoted_commit' => $this->whenLoaded('quotedCommit', fn (Commit $quoted): self => self::make($quoted)),
            'poll' => $this->whenLoaded('poll', fn (Poll $poll): PollResource => PollResource::make($poll)),
            'reply_preview' => $this->whenLoaded('replyPreview', fn (Collection $replies): array => [
                'data' => self::collection($replies),
                'next_cursor' => data_get($this->getAttribute('reply_preview'), 'next_cursor'),
            ]),
        ];
    }
}
