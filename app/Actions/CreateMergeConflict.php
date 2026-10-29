<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\ConflictSpotter;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\MergeConflict;
use App\Models\User;
use App\Queries\TomQuery;
use App\Services\Features;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Asks Tom whether two replies to a commit take opposite sides, and opens a merge conflict between them when they do.
 * A commit only ever gets one, and Tom's own replies are never one of its sides.
 */
final readonly class CreateMergeConflict
{
    /**
     * The replies Tom reads, the earliest first: a conflict is usually there by the time a thread is this long.
     */
    private const int MaxReplies = 30;

    private const int MaxReplyLength = 500;

    /**
     * However busy a thread gets, Tom reads it at most once in this many seconds.
     */
    private const int ReadEvery = 3600;

    public function __construct(private Features $features, private TomQuery $tomQuery)
    {
        //
    }

    public function handle(Commit $commit): ?MergeConflict
    {
        if (! $this->features->enabled(Feature::MergeConflicts)
            || $commit->status !== CommitStatus::Approved
            || $commit->mergeConflict()->exists()) {
            return null;
        }

        $replies = $this->replies($commit);

        if ($replies->pluck('user_id')->unique()->count() < 2
            || ! Cache::add("merge-conflict-read:{$commit->id}", true, self::ReadEvery)) {
            return null;
        }

        $response = new ConflictSpotter()->prompt(collect([
            'post' => mb_substr($commit->body, 0, self::MaxReplyLength),
            'replies' => $replies->values()->map(fn (Commit $reply, int $index): array => [
                'number' => $index + 1,
                'author' => '@'.$reply->user?->handle,
                'body' => mb_substr($reply->body, 0, self::MaxReplyLength),
            ]),
        ])->toJson());

        $ours = $replies->values()->get(is_int($response['ours'] ?? null) ? $response['ours'] - 1 : -1);
        $theirs = $replies->values()->get(is_int($response['theirs'] ?? null) ? $response['theirs'] - 1 : -1);

        if (($response['conflict'] ?? false) !== true
            || ! $ours instanceof Commit || ! $theirs instanceof Commit
            || $ours->user_id === $theirs->user_id) {
            return null;
        }

        [$ours, $theirs] = $ours->created_at->lessThanOrEqualTo($theirs->created_at) ? [$ours, $theirs] : [$theirs, $ours];

        return MergeConflict::query()->createOrFirst(['commit_id' => $commit->id], [
            'ours_id' => $ours->id,
            'theirs_id' => $theirs->id,
            'closes_at' => now()->addHours(MergeConflict::VotingHours),
        ]);
    }

    /**
     * @return Collection<int, Commit>
     */
    private function replies(Commit $commit): Collection
    {
        $tom = $this->tomQuery->get();

        return $commit->replies()
            ->where('status', CommitStatus::Approved)
            ->whereNotNull('user_id')
            ->where('body', '!=', '')
            ->when($tom instanceof User, fn ($query) => $query->where('user_id', '!=', $tom?->id))
            ->with('user')
            ->oldest()
            ->oldest('id')
            ->limit(self::MaxReplies)
            ->get();
    }
}
