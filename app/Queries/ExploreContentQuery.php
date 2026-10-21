<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ExploreContentQuery
{
    public function eligible(?User $viewer = null): CommitBuilder
    {
        return Commit::query()->approved()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)->topLevel()
            ->whereNotNull('user_id')
            ->whereHas('user', fn (EloquentBuilder $query): EloquentBuilder => $query->where('github_id', '!=', CreateTomFriendship::GITHUB_ID));
    }

    public function waiting(): Builder
    {
        return DB::table('explore_classification_work')
            ->whereNull('completed_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', now()->subMinutes(15)))
            ->whereIn('commit_id', $this->eligible()->select('commits.id'))
            ->oldest('eligible_at')->orderBy('commit_id');
    }

    public function members(ExploreTopic $topic, ?User $viewer = null): CommitBuilder
    {
        $ids = DB::table('explore_commit_topic')->select('commit_id')->where('topic_id', $topic->id);

        if ($topic->kind === 'subject') {
            $ids->union(DB::table('explore_commit_topic')->select('commit_id')
                ->whereIn('topic_id', DB::table('explore_topic_subject')->select('story_id')->where('subject_id', $topic->id))
                ->whereIn('topic_id', ExploreTopic::query()->whereNull('suppressed_at')->select('id')));
        }

        return $this->eligible($viewer)->whereIn('commits.id', $ids);
    }
}
