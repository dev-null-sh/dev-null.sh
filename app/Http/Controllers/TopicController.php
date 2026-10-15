<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Topic;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TopicController
{
    public function show(Topic $topic): Response
    {
        $topic->loadCount('commits');

        abort_if($topic->commits_count < Topic::MIN_COMMITS, 404);

        return Inertia::render('topic/show', [
            'topic' => $topic,
            'commits' => Inertia::scroll(fn () => $topic->commits()
                ->with(['user', 'promotedTopics'])
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
