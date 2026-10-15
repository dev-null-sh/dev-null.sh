<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TopicController
{
    public function show(#[CurrentUser] User $user, Topic $topic): Response
    {
        $topic->loadCount(['commits' => fn (CommitBuilder $commits): CommitBuilder => $commits->approved()]);

        abort_if($topic->commits_count < Topic::MIN_COMMITS, 404);

        return Inertia::render('topic/show', [
            'topic' => $topic,
            'commits' => Inertia::scroll(fn () => $topic->commits()
                ->visibleTo($user)
                ->with(['user', 'promotedTopics'])
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
