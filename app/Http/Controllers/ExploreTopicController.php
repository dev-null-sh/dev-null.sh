<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowExploreTopicRequest;
use App\Models\ExploreTopic;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\ExploreContentQuery;
use App\Queries\ExploreTopicsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExploreTopicController
{
    public function show(ShowExploreTopicRequest $request, #[CurrentUser] ?User $user, ExploreTopicsQuery $topics, ExploreContentQuery $content): Response
    {
        $topic = $request->topic();
        $order = $request->string('order', 'top')->value();
        $related = collect();
        if ($topic->kind === 'subject' || $order === 'related') {
            $relatedIds = $topic->kind === 'subject'
                ? DB::table('explore_topic_subject')->where('subject_id', $topic->id)->pluck('story_id')
                : DB::table('explore_topic_subject')->whereIn('subject_id',
                    DB::table('explore_topic_subject')->where('story_id', $topic->id)->select('subject_id'))->pluck('story_id');
            $related = $topics->get($user)->whereIn('id', $relatedIds)->where('id', '!=', $topic->id)->take(10)->values();
        }

        return Inertia::render('explore/show', [
            'topic' => $topics->detail($topic, $user),
            'order' => $order,
            'related' => $related,
            'commits' => $order === 'related' ? null : Inertia::scroll(fn () => $content->members($topic, $user)->withCardDataFor($user)
                ->withVisibleReplyCount($user)->withReplyPreviews($user)
                ->when($order === 'top', fn (CommitBuilder $query): CommitBuilder => $query->orderByDesc('hot_score'))
                ->when($order === 'latest', fn (CommitBuilder $query): CommitBuilder => $query->latest('created_at'))
                ->orderByDesc('id')->cursorPaginate(20)->withQueryString()),
            'reasons' => ExploreTopic::REASONS,
        ]);
    }
}
