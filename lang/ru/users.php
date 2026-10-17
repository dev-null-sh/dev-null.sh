<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => '{1} коммит|[2,4] коммита|[5,*] коммитов',
        'following' => 'в подписках',
        'followers' => '{1} подписчик|[2,4] подписчика|[5,*] подписчиков',
    ],

    'follows_you' => 'Подписан на тебя',
    'muted' => 'Скрыт',

    'follow' => [
        'follow' => 'Подписаться',
        'following' => 'В подписках',
        'unfollow' => 'Отписаться',
        'follow_label' => 'Подписаться на @:username',
        'unfollow_label' => 'Отписаться от @:username',
    ],

    'profile' => [
        'edit_on_github' => 'Изменить на GitHub',
        'view_achievements' => 'Достижения и ранг Unix',
        'sponsor' => 'Спонсировать на GitHub',
        'joined' => 'С нами с :date',
        'pasteis_received' => 'pastéis получено',
        'feed_label' => 'Лента профиля',
        'tabs' => [
            'commits' => 'Коммиты',
            'replies' => 'Ответы',
        ],
        'end' => [
            'own_first_reply' => 'Это был твой первый ответ.',
            'first_reply' => 'Это первый ответ @:username.',
            'own_first_commit' => 'Это был твой первый коммит. Все с чего-то начинают.',
            'root_commit' => 'Это корневой коммит @:username.',
        ],
        'empty' => [
            'replies_command' => 'fatal: ответов пока нет',
            'own_replies' => 'Здесь будут твои ответы. Присоединяйся к обсуждению из своего лога.',
            'replies' => '@:username пока ни на что не ответил.',
            'own_commits_command' => 'fatal: у тебя пока нет коммитов',
            'own_commits' => 'Здесь будут твои коммиты. Напиши первый в своём логе.',
            'commits_command' => 'fatal: коммитов пока нет',
            'commits' => '@:username пока ничего не закоммитил.',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'Подписки',
            'followers' => 'Подписчики',
        ],
        'tabs_label' => 'Связи',
        'back' => 'Назад к @:username',
        'empty_command' => [
            'following' => 'fatal: подписок пока нет',
            'followers' => 'fatal: подписчиков пока нет',
        ],
        'empty' => [
            'following' => '@:username пока ни на кого не подписан.',
            'followers' => 'На @:username пока никто не подписан.',
        ],
    ],

    'saved' => [
        'title' => 'Сохранённое',
        'only_you' => 'Только ты',
        'description' => 'Коммиты, к которым хочется вернуться. Сначала недавно сохранённые.',
        'end' => 'Это все твои сохранённые коммиты.',
        'empty_command' => 'fatal: сохранённых коммитов пока нет',
        'empty' => 'Сохрани коммит, чтобы найти его здесь. Сохранённые коммиты видишь только ты.',
        'explore' => 'Смотреть популярное',
    ],

    'preview' => [
        'about' => 'О @:username',
        'blocked' => 'Ты заблокировал @:username.',
        'unavailable' => 'Этот профиль недоступен.',
    ],

    'safety' => [
        'more_options' => 'Другие действия для @:username',
        'mute' => 'Скрыть @:username',
        'unmute' => 'Вернуть @:username',
        'block' => 'Заблокировать @:username',
        'unblock' => 'Разблокировать @:username',
        'block_title' => 'Заблокировать @:username?',
        'block_description' => 'Вы перестанете быть подписаны друг на друга. Никто из вас не сможет подписаться на другого, ответить ему, поставить звезду или увидеть его коммиты. Ему об этом не сообщат, но твой профиль он будет видеть как недоступный. Разблокировать можно в любой момент в настройках.',
        'cancel' => 'Отмена',
        'confirm_block' => 'Заблокировать',
    ],

    'achievements' => [
        'title' => 'Достижения разработчика',
        'label' => 'Достижения разработчика',
        'level' => 'Ур. :level · :rank',
        'summary' => '@:username · :xp XP · открыто значков: :unlocked/:total',
        'unlocked' => 'Открыто :unlocked / :total',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':xp XP до :rank',
        'max_rank' => 'Максимальный ранг (UID 0)',
        'badges_tab' => 'Значки (:count)',
        'ranks_tab' => 'Ранги Unix (:count)',
        'current' => 'ТЕКУЩИЙ',
        'achieved' => '✓ Получено',
        'requires' => 'Нужно :xp XP',
        'formula' => 'XP: 10 за коммит · 15 за день серии · 5 за полученную звезду · 5 за ответ · 2 за поставленную звезду',
    ],

    'activity' => [
        'heading' => 'Активность',
        'no_commits_yet' => 'Коммитов пока нет',
        'no_commits' => 'Нет коммитов',
        'commit_count' => '{1} :count коммит|[2,4] :count коммита|[5,*] :count коммитов',
        'summary' => ':commits :window',
        'windows' => [
            'day' => 'за последние 24 часа',
            'week' => 'за последние 7 дней',
            'month' => 'за последние 30 дней',
            'year' => 'за последний год',
        ],
        'on_date' => ':commits :date',
        'between_today' => ':commits сегодня с :start до :end',
        'between_yesterday' => ':commits вчера с :start до :end',
        'month_day' => ':day :month',
        'date_with_year' => ':day :month :year',
        'date_with_weekday' => ':weekday, :day :month',
        'weekday_day' => ':weekday, :day',
        'less' => 'Меньше',
        'more' => 'Больше',
        'months' => [
            'jan' => 'янв',
            'feb' => 'фев',
            'mar' => 'мар',
            'apr' => 'апр',
            'may' => 'май',
            'jun' => 'июн',
            'jul' => 'июл',
            'aug' => 'авг',
            'sep' => 'сен',
            'oct' => 'окт',
            'nov' => 'ноя',
            'dec' => 'дек',
        ],
        'weekdays' => [
            'sun' => 'вс',
            'mon' => 'пн',
            'tue' => 'вт',
            'wed' => 'ср',
            'thu' => 'чт',
            'fri' => 'пт',
            'sat' => 'сб',
        ],
    ],

    'streaks' => [
        'longest' => 'самая длинная серия',
        'days' => '{1} день|[2,4] дня|[5,*] дней',
        'current' => 'текущая',
    ],

    'embed' => [
        'title' => 'Встрой свой граф активности',
        'description' => 'Граф коммитов в виде SVG для README твоего профиля на GitHub или любого места, где отображается Markdown. Показывает только одобренные коммиты и обновляется примерно раз в час.',
        'theme_label' => 'Тема графа',
        'themes' => [
            'light' => 'Светлая',
            'dark' => 'Тёмная',
            'auto' => 'Авто',
        ],
        'theme_hints' => [
            'light' => 'Всегда светлая, что бы ни предпочитал читатель.',
            'dark' => 'Всегда тёмная, что бы ни предпочитал читатель.',
            'auto' => 'Подстраивается под цветовую схему читателя.',
        ],
        'preview_alt' => 'Граф активности коммитов @:username',
        'copy' => 'Копировать Markdown',
        'copied' => 'Скопировано',
        'copied_toast' => 'Markdown скопирован. Вставь его в свой README.',
        'copy_failed' => 'fatal: не удалось скопировать Markdown',
    ],

    'live' => [
        'heading' => 'Смотри, как мы его строим, в прямом эфире',
        'player_title' => 'Смотри, как мы строим /dev/null, в прямом эфире на YouTube',
    ],
];
