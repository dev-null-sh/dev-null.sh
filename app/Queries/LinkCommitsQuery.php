<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commits whose link card is the given link, the link being their topic. A link is only talked about, and its page
 * only linked and indexed, once commits of at least two people use it.
 */
final readonly class LinkCommitsQuery
{
    public const int MIN_PEOPLE = 2;

    private const int SHOWN_AROUND_COMMIT = 3;

    /**
     * Every commit of the topic, the most starred first, then the newest, or the newest first, each standing on its
     * own like in a log.
     */
    public function builder(LinkPreview $link, ?User $user, bool $isNewestFirst = false): CommitBuilder
    {
        $commits = $this->withCardData(Commit::query(), $user)
            ->where('link_preview_id', $link->id)
            ->visibleTo($user)
            ->withReplyPreviews($user);

        if (! $isNewestFirst) {
            $commits->orderByDesc('stars_count');
        }

        return $commits->latest('commits.created_at')->orderByDesc('commits.id');
    }

    /**
     * @return array{commits: int, people: int}
     */
    public function stats(LinkPreview $link, ?User $user): array
    {
        $commits = Commit::query()->where('link_preview_id', $link->id)->visibleTo($user);

        return [
            'commits' => $commits->count(),
            'people' => $commits->clone()->distinct()->count('author_id'),
        ];
    }

    /**
     * The most starred commits about the commit's link from outside its thread, which its page already shows, once
     * the link is talked about. The count is of every commit about the link, as its page lists them.
     *
     * @return array{link: LinkPreview, commits: Collection<int, Commit>, count: int}|null
     */
    public function aroundCommit(Commit $commit, ?User $user): ?array
    {
        $link = $commit->linkPreview;

        if (! $link instanceof LinkPreview || ! str_starts_with($link->url, 'https://')) {
            return null;
        }

        $stats = $this->stats($link, $user);

        if ($stats['people'] < self::MIN_PEOPLE) {
            return null;
        }

        $root = $commit->threadRootId();
        $commits = $this->builder($link, $user)
            ->withoutMutedAuthorsFor($user)
            ->withoutMutedGuildsFor($user)
            ->whereKeyNot($root)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereNull('commits.root_id')->orWhere('commits.root_id', '!=', $root))
            ->limit(self::SHOWN_AROUND_COMMIT)
            ->get();

        return $commits->isEmpty() ? null : ['link' => $link, 'commits' => $commits, 'count' => $stats['commits']];
    }

    private function withCardData(CommitBuilder $commits, ?User $user): CommitBuilder
    {
        return $commits
            ->withVisibleReplyCount($user)
            ->withCardDataFor($user)
            ->with([
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('author');
                },
            ]);
    }
}
