<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListExploreRequest;
use App\Models\ExploreTopic;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\ExploreContentQuery;
use App\Queries\ExploreTopicsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExploreController
{
    public function index(ListExploreRequest $request, #[CurrentUser] ?User $user, ExploreTopicsQuery $topics, ExploreContentQuery $content): Response
    {
        $tab = $request->string('tab', 'explore')->value();
        $ranked = $topics->get($user, category: array_key_exists($tab, ExploreTopic::CATEGORIES) ? $tab : null, personalized: $tab === 'explore');
        $today = $topics->get($user, category: 'news', period: 'today')->where('kind', 'story')->take(3)->values();
        $page = $request->integer('page', 1);
        $commits = $content->eligible($user)->whereIn('commits.id', DB::query()->fromSub($topics->memberships(), 'membership')
            ->whereIn('topic_id', $ranked->take(10)->pluck('id'))->select('commit_id'))
            ->when($user instanceof User, fn (CommitBuilder $query): CommitBuilder => $query->where('user_id', '!=', $user?->id))
            ->withCardDataFor($user)->withVisibleReplyCount($user)->withReplyPreviews($user)
            ->orderByDesc('hot_score')->orderByDesc('id')->limit(20)->get();

        return Inertia::render('explore/index', [
            'tab' => $tab,
            'categories' => ExploreTopic::CATEGORIES,
            'topics' => new LengthAwarePaginator($ranked->forPage($page, 20)->values(), $ranked->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'today_stories' => $today,
            'trending_subjects' => $topics->trendingSubjects($user),
            'recommended_commits' => $commits,
            'reasons' => ExploreTopic::REASONS,
        ]);
    }
}
