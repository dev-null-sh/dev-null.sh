<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => '提交|提交',
        'following' => '正在关注',
        'followers' => '关注者|关注者',
    ],

    'follows_you' => '关注了你',
    'muted' => '已静音',

    'follow' => [
        'follow' => '关注',
        'following' => '已关注',
        'unfollow' => '取消关注',
        'follow_label' => '关注 @:username',
        'unfollow_label' => '取消关注 @:username',
    ],

    'profile' => [
        'edit_on_github' => '在 GitHub 上编辑',
        'view_achievements' => '查看成就和 Unix 等级',
        'sponsor' => '在 GitHub 上赞助',
        'joined' => ':date 加入',
        'pasteis_received' => '收到的 pastel',
        'feed_label' => '个人主页动态',
        'tabs' => [
            'commits' => '提交',
            'replies' => '回复',
        ],
        'end' => [
            'own_first_reply' => '这是你的第一条回复。',
            'first_reply' => '这是 @:username 的第一条回复。',
            'own_first_commit' => '这是你的第一条提交。万事开头难。',
            'root_commit' => '这是 @:username 的根提交。',
        ],
        'empty' => [
            'replies_command' => 'fatal: 还没有回复',
            'own_replies' => '你的回复会显示在这里。去你的日志里参与讨论吧。',
            'replies' => '@:username 还没有回复过任何内容。',
            'own_commits_command' => 'fatal: 你还没有任何提交',
            'own_commits' => '你的提交会显示在这里。去你的日志里写下第一条吧。',
            'commits_command' => 'fatal: 还没有任何提交',
            'commits' => '@:username 还没有提交过任何内容。',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => '正在关注',
            'followers' => '关注者',
        ],
        'tabs_label' => '关系',
        'back' => '返回 @:username',
        'empty_command' => [
            'following' => 'fatal: 还没有关注任何人',
            'followers' => 'fatal: 还没有关注者',
        ],
        'empty' => [
            'following' => '@:username 还没有关注任何人。',
            'followers' => '还没有人关注 @:username。',
        ],
    ],

    'saved' => [
        'title' => '收藏',
        'only_you' => '仅自己可见',
        'description' => '你想回头再看的提交，最近收藏的排在最前。',
        'end' => '已经到底了，没有更多收藏的提交。',
        'empty_command' => 'fatal: 还没有收藏的提交',
        'empty' => '收藏一条提交，就能在这里找到它。你的收藏是私密的。',
        'explore' => '浏览热门',
    ],

    'preview' => [
        'about' => '关于 @:username',
        'blocked' => '你已屏蔽 @:username。',
        'unavailable' => '此个人主页不可用。',
    ],

    'safety' => [
        'more_options' => '@:username 的更多选项',
        'mute' => '静音 @:username',
        'unmute' => '取消静音 @:username',
        'block' => '屏蔽 @:username',
        'unblock' => '取消屏蔽 @:username',
        'block_title' => '屏蔽 @:username？',
        'block_description' => '你们将互相取消关注。你们双方都无法关注、回复对方或为对方加星标，也看不到对方的提交。对方不会收到通知，但会看到你的个人主页不可用。你可以随时在设置中取消屏蔽。',
        'cancel' => '取消',
        'confirm_block' => '屏蔽',
    ],

    'achievements' => [
        'title' => '开发者成就',
        'label' => '开发者成就',
        'level' => 'Lv. :level · :rank',
        'summary' => '@:username · :xp XP · 已解锁 :unlocked/:total 枚徽章',
        'unlocked' => '已解锁 :unlocked / :total',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => '距离 :rank 还差 :xp XP',
        'max_rank' => '最高等级 (UID 0)',
        'badges_tab' => '徽章 (:count)',
        'ranks_tab' => 'Unix 等级 (:count)',
        'current' => '当前',
        'achieved' => '✓ 已达成',
        'requires' => '需要 :xp XP',
        'formula' => 'XP：每条提交 10 · 每天连续 15 · 每获得一个星标 5 · 每条回复 5 · 每送出一个星标 2',
    ],

    'activity' => [
        'heading' => '提交活动',
        'no_commits_yet' => '还没有提交',
        'no_commits' => '没有提交',
        'commit_count' => ':count 条提交|:count 条提交',
        'summary' => ':window :commits',
        'windows' => [
            'day' => '过去 24 小时',
            'week' => '过去 7 天',
            'month' => '过去 30 天',
            'year' => '过去一年',
        ],
        'on_date' => ':date :commits',
        'between_today' => '今天 :start 至 :end :commits',
        'between_yesterday' => '昨天 :start 至 :end :commits',
        'month_day' => ':month:day日',
        'date_with_year' => ':year年:month:day日',
        'date_with_weekday' => ':month:day日 :weekday',
        'weekday_day' => ':weekday :day日',
        'less' => '少',
        'more' => '多',
        'months' => [
            'jan' => '1月',
            'feb' => '2月',
            'mar' => '3月',
            'apr' => '4月',
            'may' => '5月',
            'jun' => '6月',
            'jul' => '7月',
            'aug' => '8月',
            'sep' => '9月',
            'oct' => '10月',
            'nov' => '11月',
            'dec' => '12月',
        ],
        'weekdays' => [
            'sun' => '周日',
            'mon' => '周一',
            'tue' => '周二',
            'wed' => '周三',
            'thu' => '周四',
            'fri' => '周五',
            'sat' => '周六',
        ],
    ],

    'streaks' => [
        'longest' => '最长连续',
        'days' => '天|天',
        'current' => '当前',
    ],

    'embed' => [
        'title' => '嵌入你的活动图',
        'description' => '将你的提交活动图以 SVG 形式放到 GitHub 个人主页 README 中，或任何支持 Markdown 的地方。它只显示已通过的提交，大约每小时刷新一次。',
        'theme_label' => '图表主题',
        'themes' => [
            'light' => '浅色',
            'dark' => '深色',
            'auto' => '自动',
        ],
        'theme_hints' => [
            'light' => '始终为浅色，不论读者偏好如何。',
            'dark' => '始终为深色，不论读者偏好如何。',
            'auto' => '跟随读者的配色方案。',
        ],
        'preview_alt' => '@:username 的提交活动图',
        'copy' => '复制 Markdown',
        'copied' => '已复制',
        'copied_toast' => '已复制 Markdown，粘贴到你的 README 中即可。',
        'copy_failed' => 'fatal: 无法复制 Markdown',
    ],

    'live' => [
        'heading' => '看我们直播开发',
        'player_title' => '在 YouTube 上看我们直播开发 /dev/null',
    ],
];
