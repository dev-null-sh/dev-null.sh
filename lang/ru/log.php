<?php

declare(strict_types=1);

return [
    'titles' => [
        'following' => 'Подписки',
        'latest' => 'Новое',
        'trending' => 'Популярное',
    ],

    'tabs_label' => 'Логи',
    'new_commits' => '{1} :count новый коммит|[2,4] :count новых коммита|[5,*] :count новых коммитов',
    'end' => 'Это корневой коммит. Раньше ничего нет.',

    'empty' => [
        'following_command' => 'fatal: в твоём логе пока нет коммитов',
        'following' => 'В логе видны твои коммиты и коммиты тех, на кого ты подписан.',
        'suggestions_intro' => 'Вот что недавно закоммитили некоторые разработчики:',
        'find_someone_before' => 'Найди, на кого подписаться, в разделе',
        'find_someone_after' => '.',
        'command' => 'fatal: коммитов пока нет',
        'nobody' => 'Никто ещё ничего не закоммитил. Стань первым. Это просто текст, всё получится.',
    ],

    'suggestions' => [
        'follow' => 'Подписаться',
        'follow_label' => 'Подписаться на @:username',
        'browse_before' => 'Или загляни в раздел',
        'browse_after' => '— там ещё больше.',
    ],
];
