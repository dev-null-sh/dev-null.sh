<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => '커밋|커밋',
        'following' => '팔로잉',
        'followers' => '팔로워|팔로워',
    ],

    'follows_you' => '나를 팔로우함',
    'muted' => '뮤트함',

    'follow' => [
        'follow' => '팔로우',
        'following' => '팔로잉',
        'unfollow' => '팔로우 취소',
        'follow_label' => '@:username 팔로우',
        'unfollow_label' => '@:username 팔로우 취소',
    ],

    'profile' => [
        'edit_on_github' => 'GitHub에서 수정',
        'view_achievements' => '업적과 Unix 등급 보기',
        'sponsor' => 'GitHub에서 후원하기',
        'joined' => '가입일: :date',
        'pasteis_received' => '받은 pastel|받은 pastel',
        'feed_label' => '프로필 피드',
        'tabs' => [
            'commits' => '커밋',
            'replies' => '답글',
        ],
        'end' => [
            'own_first_reply' => '나의 첫 답글이에요.',
            'first_reply' => '@:username 님의 첫 답글이에요.',
            'own_first_commit' => '나의 첫 커밋이에요. 누구나 처음은 있죠.',
            'root_commit' => '@:username 님의 루트 커밋이에요.',
        ],
        'empty' => [
            'replies_command' => 'fatal: 아직 답글이 없어요',
            'own_replies' => '내 답글이 여기에 표시돼요. 로그에서 대화에 참여해 보세요.',
            'replies' => '@:username 님은 아직 답글을 단 적이 없어요.',
            'own_commits_command' => 'fatal: 아직 커밋이 없어요',
            'own_commits' => '내 커밋이 여기에 표시돼요. 로그에서 첫 커밋을 해 보세요.',
            'commits_command' => 'fatal: 아직 커밋이 없어요',
            'commits' => '@:username 님은 아직 커밋한 적이 없어요.',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => '팔로잉',
            'followers' => '팔로워',
        ],
        'tabs_label' => '관계',
        'back' => '@:username 님에게 돌아가기',
        'empty_command' => [
            'following' => 'fatal: 아직 팔로우하는 사람이 없어요',
            'followers' => 'fatal: 아직 팔로워가 없어요',
        ],
        'empty' => [
            'following' => '@:username 님은 아직 아무도 팔로우하지 않아요.',
            'followers' => '아직 @:username 님을 팔로우하는 사람이 없어요.',
        ],
    ],

    'saved' => [
        'title' => '저장됨',
        'only_you' => '나만 보기',
        'description' => '나중에 다시 보고 싶은 커밋이에요. 최근에 저장한 순서로 보여요.',
        'end' => '저장한 커밋을 모두 봤어요.',
        'empty_command' => 'fatal: 아직 저장한 커밋이 없어요',
        'empty' => '커밋을 저장하면 여기에서 찾을 수 있어요. 저장한 커밋은 비공개예요.',
        'explore' => '트렌딩 둘러보기',
    ],

    'preview' => [
        'about' => '@:username 님 소개',
        'blocked' => '@:username 님을 차단했어요.',
        'unavailable' => '이 프로필을 볼 수 없어요.',
    ],

    'safety' => [
        'more_options' => '@:username 님에 대한 옵션 더 보기',
        'mute' => '@:username 뮤트',
        'unmute' => '@:username 뮤트 해제',
        'block' => '@:username 차단',
        'unblock' => '@:username 차단 해제',
        'block_title' => '@:username 님을 차단할까요?',
        'block_description' => '서로 팔로우가 해제돼요. 서로 팔로우, 답글, 스타를 할 수 없고, 서로의 커밋도 볼 수 없어요. 상대에게 알리지는 않지만, 상대에게는 내 프로필이 볼 수 없는 상태로 보여요. 설정에서 언제든 차단을 해제할 수 있어요.',
        'cancel' => '취소',
        'confirm_block' => '차단',
    ],

    'achievements' => [
        'title' => '개발자 업적',
        'label' => '개발자 업적',
        'level' => 'Lv. :level · :rank',
        'summary' => '@:username · :xp XP · 배지 :unlocked/:total개 획득',
        'unlocked' => ':unlocked / :total 획득',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':rank까지 :xp XP',
        'max_rank' => '최고 등급 (UID 0)',
        'badges_tab' => '배지 (:count)',
        'ranks_tab' => 'Unix 등급 (:count)',
        'current' => '현재',
        'achieved' => '✓ 달성',
        'requires' => ':xp XP 필요',
        'formula' => 'XP: 커밋당 10 · 연속 기록 하루당 15 · 받은 스타당 5 · 답글당 5 · 준 스타당 2',
    ],

    'activity' => [
        'heading' => '커밋 활동',
        'no_commits_yet' => '아직 커밋이 없어요',
        'no_commits' => '커밋 없음',
        'commit_count' => '커밋 :count개|커밋 :count개',
        'summary' => ':window 동안 :commits',
        'windows' => [
            'day' => '최근 24시간',
            'week' => '최근 7일',
            'month' => '최근 30일',
            'year' => '최근 1년',
        ],
        'on_date' => ':date에 :commits',
        'between_today' => '오늘 :start~:end에 :commits',
        'between_yesterday' => '어제 :start~:end에 :commits',
        'month_day' => ':month :day일',
        'date_with_year' => ':year년 :month :day일',
        'date_with_weekday' => ':month :day일 (:weekday)',
        'weekday_day' => ':day일 (:weekday)',
        'less' => '적음',
        'more' => '많음',
        'months' => [
            'jan' => '1월',
            'feb' => '2월',
            'mar' => '3월',
            'apr' => '4월',
            'may' => '5월',
            'jun' => '6월',
            'jul' => '7월',
            'aug' => '8월',
            'sep' => '9월',
            'oct' => '10월',
            'nov' => '11월',
            'dec' => '12월',
        ],
        'weekdays' => [
            'sun' => '일',
            'mon' => '월',
            'tue' => '화',
            'wed' => '수',
            'thu' => '목',
            'fri' => '금',
            'sat' => '토',
        ],
    ],

    'streaks' => [
        'longest' => '최장 연속',
        'days' => '일|일',
        'current' => '현재',
    ],

    'embed' => [
        'title' => '활동 그래프 삽입하기',
        'description' => '커밋 그래프를 SVG로 GitHub 프로필 README나 Markdown이 렌더링되는 어디에든 넣을 수 있어요. 승인된 커밋만 표시되며 약 1시간마다 새로 고쳐져요.',
        'theme_label' => '그래프 테마',
        'themes' => [
            'light' => '라이트',
            'dark' => '다크',
            'auto' => '자동',
        ],
        'theme_hints' => [
            'light' => '보는 사람의 설정과 관계없이 항상 라이트.',
            'dark' => '보는 사람의 설정과 관계없이 항상 다크.',
            'auto' => '보는 사람의 색상 모드를 따라요.',
        ],
        'preview_alt' => '@:username 님의 커밋 활동 그래프',
        'copy' => 'Markdown 복사',
        'copied' => '복사됨',
        'copied_toast' => 'Markdown을 복사했어요. README에 붙여 넣으세요.',
        'copy_failed' => 'fatal: Markdown을 복사하지 못했어요',
    ],

    'live' => [
        'heading' => '개발 과정을 라이브로 보세요',
        'player_title' => 'YouTube에서 /dev/null 라이브 개발 보기',
    ],
];
