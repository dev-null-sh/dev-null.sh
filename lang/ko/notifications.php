<?php

declare(strict_types=1);

return [
    'title' => '알림',
    'filter_label' => '알림 필터',

    'filters' => [
        'all' => '전체',
        'replies' => '답글',
        'quotes' => '인용',
        'stars' => '스타',
        'mentions' => '멘션',
        'follows' => '팔로우',
        'moderation' => '검토',
    ],

    'empty' => [
        'all' => '아직 아무것도 없어요. 내 커밋에 대한 팔로우, 스타, 답글, 인용, 멘션, 검토 소식이 여기에 표시돼요.',
        'replies' => '아직 답글이 없어요. 누군가 내 커밋에 답글을 달면 여기에 표시돼요.',
        'quotes' => '아직 인용이 없어요. 누군가 내 커밋을 인용하면 여기에 표시돼요.',
        'stars' => '아직 스타가 없어요. 누군가 내 커밋에 스타를 주면 여기에 표시돼요.',
        'mentions' => '아직 멘션이 없어요. 누군가 커밋에서 나를 멘션하면 여기에 표시돼요.',
        'follows' => '아직 팔로워가 없어요. 누군가 나를 팔로우하면 여기에 표시돼요.',
        'moderation' => '검토 소식이 없어요. 승인되거나 거부된 커밋이 여기에 표시돼요.',
    ],

    'show_all' => '모든 알림 보기',
    'new_label' => '개의 새 알림|개의 새 알림',
    'loading_more' => '알림 더 불러오는 중',
    'end' => '가장 오래된 알림이에요. 이전에는 아무것도 없어요.',
    'last_visit' => '위의 알림은 마지막 방문 이후 새로 온 알림이에요',
    'new' => '새 알림.',
    'try_again' => '다시 시도',

    'groups' => [
        'followers' => [
            'count' => '팔로워 :count명|팔로워 :count명',
            'hide' => '팔로워 숨기기',
            'loading' => '팔로워 불러오는 중…',
            'failed' => 'fatal: 팔로워를 불러오지 못했어요',
            'older' => '이전 팔로워 보기',
        ],
        'stars' => [
            'count' => '스타 :count개|스타 :count개',
            'hide' => '스타 숨기기',
            'loading' => '스타 불러오는 중…',
            'failed' => 'fatal: 스타를 불러오지 못했어요',
            'older' => '이전 스타 보기',
        ],
        'updates' => [
            'count' => '소식 :count개|소식 :count개',
            'hide' => '소식 숨기기',
            'loading' => '소식 불러오는 중…',
            'failed' => 'fatal: 소식을 불러오지 못했어요',
            'older' => '이전 소식 보기',
        ],
    ],

    'actions' => [
        'followed' => '나를 팔로우했어요',
        'replied' => '내 커밋에 답글을 달았어요',
        'quoted' => '내 커밋을 인용했어요',
        'starred' => '내 커밋에 스타를 줬어요',
        'mentioned' => '나를 멘션했어요',
        'approved' => '내 커밋이 승인되었어요',
        'rejected' => '내 커밋이 거부되었어요',
    ],

    'and_others' => '외 :count명|외 :count명',

    'statuses' => [
        'approved' => '승인됨',
        'rejected' => '거부됨',
    ],

    'reason' => '사유:',
    'rejected_note' => '이 소식과 거부된 커밋은 나만 볼 수 있어요.',
];
