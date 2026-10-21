<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\ExplorePreference;
use App\Models\ExploreTopic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use stdClass;

/** @phpstan-type TopicResult array{id: string, kind: string, key: string, title: string, categories: list<string>, summary: string|null, summary_generated_at: string|null, commits_count: int, contributors_count: int, latest_activity_at: string, score: float, url: string, subjects: list<array{id: string, title: string, url: string}>} */
final readonly class ExploreTopicsQuery
{
    public function __construct(private ExploreContentQuery $content)
    {
        //
    }

    public function memberships(): Builder
    {
        return DB::table('explore_commit_topic')->select(['topic_id', 'commit_id'])
            ->union(DB::table('explore_commit_topic as membership')
                ->join('explore_topic_subject as parents', 'parents.story_id', '=', 'membership.topic_id')
                ->whereIn('membership.topic_id', ExploreTopic::query()->whereNull('suppressed_at')->select('id'))
                ->select(['parents.subject_id as topic_id', 'membership.commit_id']));
    }

    /** @return array{CarbonImmutable|null, CarbonImmutable} */
    public function window(string $period, string $timezone): array
    {
        $end = CarbonImmutable::now();

        return [$period === 'all' ? null : ($period === 'today' ? $end->setTimezone($timezone)->startOfDay()->utc() : $end->subHours(24)), $end];
    }

    /** @return Collection<int, TopicResult> */
    public function get(?User $viewer = null, ?string $category = null, string $period = '24h', ?string $timezone = null, bool $personalized = false): Collection
    {
        $timezone ??= $viewer->timezone ?? config()->string('app.timezone');
        [$start, $end] = $this->window($period, $timezone);
        $eligible = $this->content->eligible($viewer)->select(['commits.id', 'commits.user_id', 'commits.hot_score', 'commits.created_at'])
            ->when($start !== null, fn (CommitBuilder $query): CommitBuilder => $query->where('commits.created_at', '>=', $start))
            ->where('commits.created_at', '<=', $end);
        $perAuthor = DB::query()->fromSub($this->memberships(), 'membership')
            ->joinSub($eligible, 'commits', 'commits.id', '=', 'membership.commit_id')
            ->select(['membership.topic_id', 'commits.user_id'])
            ->selectRaw('count(distinct commits.id) as commits_count, max(commits.created_at) as latest_at, max(power(2.0, commits.hot_score - ?)) as score', [$end->getTimestamp() / 10800])
            ->groupBy('membership.topic_id', 'commits.user_id');
        $stats = DB::query()->fromSub($perAuthor, 'authors')->select('topic_id')
            ->selectRaw('sum(commits_count) as commits_count, count(*) as contributors_count, max(latest_at) as latest_at, sum(score) as score')->groupBy('topic_id');
        $invalidSources = DB::table('explore_summary_sources')->select('topic_id')
            ->whereNotIn('commit_id', $this->content->eligible($viewer)->select('commits.id'));
        $topics = ExploreTopic::query()->joinSub($stats, 'stats', 'stats.topic_id', '=', 'explore_topics.id')
            ->select(['explore_topics.*', 'stats.commits_count', 'stats.contributors_count', 'stats.latest_at', 'stats.score'])
            ->whereNull('suppressed_at')->whereNotIn('explore_topics.id', $invalidSources)
            ->where(fn (EloquentBuilder $query): EloquentBuilder => $query->where('kind', 'subject')->orWhereNotNull('summary_generated_at'))
            ->when($category !== null, fn (EloquentBuilder $query): EloquentBuilder => $query->whereJsonContains('categories', $category))
            ->when($viewer instanceof User, fn (EloquentBuilder $query): EloquentBuilder => $query->whereNotIn('explore_topics.id',
                DB::table('explore_hidden_topics')->where('user_id', $viewer?->id)->select('topic_id')))->get();
        $following = collect();
        $starred = collect();
        if ($personalized && $viewer instanceof User && (ExplorePreference::query()->find($viewer->id)?->preferences['personalized'] ?? true)) {
            $following = DB::query()->fromSub($this->memberships(), 'm')->join('commits', 'commits.id', '=', 'm.commit_id')
                ->whereIn('commits.user_id', $viewer->following()->select('users.id'))->pluck('m.topic_id');
            $starred = DB::query()->fromSub($this->memberships(), 'm')->join('stars', 'stars.commit_id', '=', 'm.commit_id')
                ->where('stars.user_id', $viewer->id)->pluck('m.topic_id');
        }

        $subjects = DB::table('explore_topic_subject')->get()->map(function (stdClass $row): array {
            $link = new Fluent((array) $row);

            return ['story_id' => $link->string('story_id')->value(), 'subject_id' => $link->string('subject_id')->value()];
        });
        $allowed = $topics->keyBy('id');

        return $topics->map(function (ExploreTopic $topic) use ($following, $starred, $subjects, $allowed): array {
            $parents = $subjects->where('story_id', $topic->id)->map(fn (array $link): ?ExploreTopic => $allowed->get($link['subject_id']))->filter();
            $score = $topic->score * ($following->contains($topic->id) ? 1.25 : 1) * ($starred->contains($topic->id) ? 1.25 : 1);

            return [
                'id' => $topic->id, 'kind' => $topic->kind, 'key' => $topic->key, 'title' => $topic->title,
                'categories' => $topic->categories, 'summary' => $topic->summary,
                'summary_generated_at' => $topic->summary_generated_at?->toIso8601String(),
                'commits_count' => $topic->commits_count,
                'contributors_count' => $topic->contributors_count,
                'latest_activity_at' => $topic->latest_at->toIso8601String(),
                'score' => $score, 'url' => $topic->url(),
                'subjects' => array_values($parents->map(fn (ExploreTopic $parent): array => ['id' => $parent->id, 'title' => $parent->title, 'url' => $parent->url()])->all()),
            ];
        })->sort(fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($b['latest_activity_at'], $a['latest_activity_at']) ?: strcmp($a['id'], $b['id']))->values();
    }

    /** @return Collection<int, array{category: string, topic: TopicResult}> */
    public function trendingSubjects(?User $viewer = null): Collection
    {
        $subjects = $this->get($viewer)->where('kind', 'subject');
        $trending = collect();
        foreach (['ai', 'web-development', 'open-source'] as $category) {
            $subject = $subjects->first(fn (array $subject): bool => in_array($category, $subject['categories'], true));
            if ($subject !== null) {
                $trending->push(['category' => $category, 'topic' => $subject]);
            }
        }

        return $trending;
    }

    /** @return array<string, mixed>|null */
    public function detail(ExploreTopic $topic, ?User $viewer = null): ?array
    {
        $row = $this->get($viewer, period: 'all')->firstWhere('id', $topic->id);
        if ($row === null) {
            return null;
        }

        $row['sources'] = $this->content->eligible($viewer)->whereIn('id',
            DB::table('explore_summary_sources')->select('commit_id')->where('topic_id', $topic->id))
            ->with('user')->get(['id', 'user_id', 'created_at'])->map(fn (Commit $commit): array => [
                'url' => route('commit.show', [$commit->user, $commit]), 'author' => $commit->user?->username,
            ])->all();

        return $row;
    }
}
