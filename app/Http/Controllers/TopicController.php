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
        $topic->loadCount('posts');

        return Inertia::render('topic/show', [
            'topic' => $topic,
            'posts' => Inertia::scroll(fn () => $topic->posts()
                ->with('user')
                ->latest()
                ->latest('id')
                ->cursorPaginate(20)),
        ]);
    }
}
