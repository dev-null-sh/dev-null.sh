<?php

declare(strict_types=1);

return [
    'titles' => [
        'following' => 'Following',
        'latest' => 'Latest',
        'trending' => 'Trending',
    ],

    'tabs_label' => 'Logs',
    'new_commits' => ':count new commit|:count new commits',
    'end' => "That's the root commit. There's nothing before it.",

    'empty' => [
        'following_command' => 'fatal: your log does not have any commits yet',
        'following' => 'Your log shows your commits and commits from people you follow.',
        'suggestions_intro' => 'Here is what a few developers committed lately:',
        'find_someone_before' => 'Find someone worth following on',
        'find_someone_after' => '.',
        'command' => 'fatal: no commits yet',
        'nobody' => "Nobody has committed anything. Be the first. It's just plain text; you'll be fine.",
    ],

    'suggestions' => [
        'follow' => 'Follow',
        'follow_label' => 'Follow @:username',
        'browse_before' => 'Or browse',
        'browse_after' => 'for more.',
    ],
];
