<?php

declare(strict_types=1);

return [
    'body' => '본문',
    'placeholder' => '무엇을 배포했나요?',
    'poll_placeholder' => '질문하기',
    'commit' => '커밋',
    'committing' => '커밋 중…',
    'commit_title' => '커밋 (:keys)',

    'dialog' => [
        'title' => '커밋 작성',
        'description' => '제출하면 커밋이 내 로그에 추가돼요.',
    ],

    'guest' => [
        'title' => '개발자를 위한 소셜 네트워크.',
        'description' => '아래의 모든 사람이 GitHub로 로그인해서 일반 텍스트로 커밋했어요. 이제 당신 차례예요.',
        'sign_in_to_commit' => 'GitHub로 로그인하고 커밋하기',
        'profile' => '프로필은 GitHub에서 가져와요. 따로 입력할 건 없어요.',
        'sign_in' => 'GitHub로 로그인',
    ],

    'schedule' => [
        'button' => '예약',
        'submit_at' => '제출 시간',
        'commit' => '커밋 예약',
        'scheduling' => '예약 중…',
        'commit_title' => '커밋 예약 (:keys)',
        'nonexistent_time' => '내 시간대에 존재하지 않는 시간이에요. 다른 시간을 선택해 주세요.',
        'help_admin' => ':timezone. 30일 이내. 제출 전까지 비공개이며, 커밋은 자동으로 승인돼요.',
        'help_review' => ':timezone. 30일 이내. 제출 전까지 비공개이며, 제출 후 검토를 거쳐요. 자동으로 게시되지 않아요.',
        'scheduled_commits' => '예약된 커밋 →',
    ],

    'poll' => [
        'add' => '투표 추가',
        'images_or_poll' => '커밋에는 이미지나 투표 중 하나만 넣을 수 있어요',
        'legend' => '투표',
        'option' => '항목 :number',
        'remove_option' => '항목 :number 삭제',
        'remove_option_title' => '항목 삭제',
        'add_option' => '항목 추가',
        'length' => '투표 기간',
        'remove' => '투표 삭제',
        'hours' => ':count시간|:count시간',
        'days' => ':count일|:count일',
    ],

    'images' => [
        'invalid' => '10MB 이하의 JPEG, PNG, WebP 이미지만 올릴 수 있어요.',
        'drop_to_attach' => '이미지를 놓아서 첨부하세요',
        'too_many' => '커밋당 이미지는 최대 :max개까지예요.',
        'attach' => '이미지 첨부',
        'attach_title' => '이미지 최대 :max개 첨부',
        'attached' => '첨부된 이미지',
        'attached_alt' => '첨부 이미지 :number',
        'remove' => '이미지 :number 삭제',
        'edit_description' => '이미지 :number 설명 편집',
        'add_description' => '이미지 :number에 설명 추가',
        'description_label' => '이미지 :number 설명',
        'description_placeholder' => '이미지를 볼 수 없는 사람을 위해 설명해 주세요',
        'done' => '완료',
    ],

    'preview' => [
        'label' => '미리보기',
        'title' => '미리보기 (:keys)',
        'empty' => '아직 미리 볼 내용이 없어요.',
        'link_card' => '커밋하면 링크 카드로 펼쳐져요.',
    ],

    'link_card' => [
        'title' => '카드로 보여 줄 링크 선택',
        'label' => '링크 카드',
        'sr_label' => '링크 카드:',
        'none' => '카드 없음',
    ],

    'counter' => [
        'remaining' => ':count자 남음|:count자 남음',
        'fold_hint' => '피드에는 처음 :length자 또는 :lines줄까지 보이고, 나머지는 “더 보기”로 표시돼요.',
    ],

    'stash' => [
        'restored' => '보내지 않은 초안을 복원했어요',
        'drop_title' => '복원한 초안 버리기',
    ],

    'mentions' => [
        'emoji_label' => '이모지 넣기',
        'label' => '개발자 멘션',
        'following' => '내가 팔로우하는 사람',
        'searching' => '검색 중…',
        'no_match' => "fatal: ':query'와(과) 일치하는 개발자가 없어요",
    ],

    'secrets' => [
        'warning' => '민감한 텍스트 경고',
        'detected' => '시크릿으로 보이는 내용이 감지됐어요|시크릿으로 보이는 내용이 감지됐어요',
        'checked_locally' => '내 기기에서 확인했어요. 감지된 시크릿이 있는 동안에는 이 초안이 저장되지 않아요.',
        'select' => '시크릿으로 보이는 내용 선택',
        'select_numbered' => '시크릿으로 보이는 내용 :number 선택',
        'remove_detected' => '감지된 시크릿 삭제',
        'confirm_title' => '시크릿일 수 있는 내용을 게시할까요?',
        'confirm_description' => '이 텍스트에 인증 정보가 포함되어 있을 수 있어요. 게시하기 전에 삭제하거나, 안전한 예시인 경우에만 계속하세요.',
        'keep_editing' => '계속 편집',
        'remove' => '시크릿 삭제',
        'publish_anyway' => '그래도 게시',
    ],

    'quote' => [
        'button' => '인용',
        'button_title' => '인용 (:keys)',
        'unsent' => '보내지 않은 인용이 있어요',
        'title' => ':handle 인용',
        'description' => '내 커밋을 작성하세요. 이 커밋은 그 아래에 삽입돼요.',
        'label' => '내 커밋',
        'placeholder' => '의견 추가',
        'cancel' => '취소',
        'submit' => '인용',
        'quoting' => '인용 중…',
    ],
];
