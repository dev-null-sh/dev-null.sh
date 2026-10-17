<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'filter_label' => 'Filter notifications',

    'filters' => [
        'all' => 'All',
        'replies' => 'Replies',
        'quotes' => 'Quotes',
        'stars' => 'Stars',
        'mentions' => 'Mentions',
        'follows' => 'Follows',
        'moderation' => 'Moderation',
    ],

    'empty' => [
        'all' => 'Nothing here yet. Follows, stars, replies, quotes, mentions, and moderation updates for your commits will show up here.',
        'replies' => 'No replies yet. When someone replies to one of your commits, it shows up here.',
        'quotes' => 'No quotes yet. When someone quotes one of your commits, it shows up here.',
        'stars' => 'No stars yet. When someone stars one of your commits, it shows up here.',
        'mentions' => 'No mentions yet. When someone mentions you in a commit, it shows up here.',
        'follows' => 'No followers yet. When someone follows you, it shows up here.',
        'moderation' => 'No moderation updates. Approved and rejected commits show up here.',
    ],

    'show_all' => 'Show all notifications',
    'new_label' => 'new notification|new notifications',
    'loading_more' => 'Loading more notifications',
    'end' => "That's the oldest notification. There's nothing before it.",
    'last_visit' => 'Notifications above are new since your last visit',
    'new' => 'New.',
    'try_again' => 'Try again',

    'groups' => [
        'followers' => [
            'count' => ':count follower|:count followers',
            'hide' => 'Hide followers',
            'loading' => 'Loading followers…',
            'failed' => 'fatal: could not load followers',
            'older' => 'Show older followers',
        ],
        'stars' => [
            'count' => ':count star|:count stars',
            'hide' => 'Hide stars',
            'loading' => 'Loading stars…',
            'failed' => 'fatal: could not load stars',
            'older' => 'Show older stars',
        ],
        'updates' => [
            'count' => ':count update|:count updates',
            'hide' => 'Hide updates',
            'loading' => 'Loading updates…',
            'failed' => 'fatal: could not load updates',
            'older' => 'Show older updates',
        ],
    ],

    'actions' => [
        'followed' => 'followed you',
        'replied' => 'replied to your commit',
        'quoted' => 'quoted your commit',
        'starred' => 'starred your commit',
        'mentioned' => 'mentioned you',
        'approved' => 'Your commit was approved',
        'rejected' => 'Your commit was rejected',
    ],

    'and_others' => 'and :count other|and :count others',

    'statuses' => [
        'approved' => 'approved',
        'rejected' => 'rejected',
    ],

    'reason' => 'reason:',
    'rejected_note' => 'Only you can see this update and your rejected commit.',
];
