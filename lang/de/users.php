<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => 'Commit|Commits',
        'following' => 'Folge ich',
        'followers' => 'Follower|Follower',
    ],

    'follows_you' => 'Folgt dir',
    'muted' => 'Stummgeschaltet',

    'follow' => [
        'follow' => 'Folgen',
        'following' => 'Folge ich',
        'unfollow' => 'Entfolgen',
        'follow_label' => '@:username folgen',
        'unfollow_label' => '@:username entfolgen',
    ],

    'profile' => [
        'edit_on_github' => 'Auf GitHub bearbeiten',
        'view_achievements' => 'Erfolge und Unix-Rang ansehen',
        'sponsor' => 'Auf GitHub sponsern',
        'joined' => 'Dabei seit :date',
        'pasteis_received' => 'Pastéis erhalten',
        'feed_label' => 'Profil-Feed',
        'tabs' => [
            'commits' => 'Commits',
            'replies' => 'Antworten',
        ],
        'end' => [
            'own_first_reply' => 'Das war deine erste Antwort.',
            'first_reply' => 'Das ist die erste Antwort von @:username.',
            'own_first_commit' => 'Das war dein erster Commit. Jeder fängt mal an.',
            'root_commit' => 'Das ist der Root-Commit von @:username.',
        ],
        'empty' => [
            'replies_command' => 'fatal: noch keine Antworten',
            'own_replies' => 'Deine Antworten erscheinen hier. Steig über dein Log in eine Unterhaltung ein.',
            'replies' => '@:username hat noch auf nichts geantwortet.',
            'own_commits_command' => 'fatal: du hast noch keine Commits',
            'own_commits' => 'Deine Commits erscheinen hier. Schreib deinen ersten in deinem Log.',
            'commits_command' => 'fatal: noch keine Commits',
            'commits' => '@:username hat noch nichts committet.',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'Folge ich',
            'followers' => 'Follower',
        ],
        'tabs_label' => 'Verbindungen',
        'back' => 'Zurück zu @:username',
        'empty_command' => [
            'following' => 'fatal: folgt noch niemandem',
            'followers' => 'fatal: noch keine Follower',
        ],
        'empty' => [
            'following' => '@:username folgt noch niemandem.',
            'followers' => 'Noch folgt niemand @:username.',
        ],
    ],

    'saved' => [
        'title' => 'Gespeichert',
        'only_you' => 'Nur du',
        'description' => 'Commits, zu denen du zurückkehren willst. Zuletzt gespeicherte zuerst.',
        'end' => 'Du bist am Ende deiner gespeicherten Commits.',
        'empty_command' => 'fatal: noch keine gespeicherten Commits',
        'empty' => 'Speichere einen Commit, um ihn hier wiederzufinden. Deine gespeicherten Commits sind privat.',
        'explore' => 'Trends entdecken',
    ],

    'preview' => [
        'about' => 'Über @:username',
        'blocked' => 'Du hast @:username blockiert.',
        'unavailable' => 'Dieses Profil ist nicht verfügbar.',
    ],

    'safety' => [
        'more_options' => 'Weitere Optionen für @:username',
        'mute' => '@:username stummschalten',
        'unmute' => 'Stummschaltung für @:username aufheben',
        'block' => '@:username blockieren',
        'unblock' => '@:username entblocken',
        'block_title' => '@:username blockieren?',
        'block_description' => 'Ihr folgt euch dann nicht mehr. Keiner von euch kann dem anderen folgen, antworten oder Sterne geben oder die Commits des anderen sehen. Die Person erfährt es nicht, sieht dein Profil aber als nicht verfügbar. Du kannst die Blockierung jederzeit in deinen Einstellungen aufheben.',
        'cancel' => 'Abbrechen',
        'confirm_block' => 'Blockieren',
    ],

    'achievements' => [
        'title' => 'Entwickler-Erfolge',
        'label' => 'Entwickler-Erfolge',
        'level' => 'Lvl. :level · :rank',
        'summary' => '@:username · :xp XP · :unlocked/:total Abzeichen freigeschaltet',
        'unlocked' => ':unlocked / :total freigeschaltet',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => 'Noch :xp XP bis :rank',
        'max_rank' => 'Höchster Rang (UID 0)',
        'badges_tab' => 'Abzeichen (:count)',
        'ranks_tab' => 'Unix-Ränge (:count)',
        'current' => 'AKTUELL',
        'achieved' => '✓ Erreicht',
        'requires' => 'Benötigt :xp XP',
        'formula' => 'XP: 10/Commit · 15/Streak-Tag · 5/erhaltener Stern · 5/Antwort · 2/vergebener Stern',
    ],

    'activity' => [
        'heading' => 'Commit-Aktivität',
        'no_commits_yet' => 'Noch keine Commits',
        'no_commits' => 'Keine Commits',
        'commit_count' => ':count Commit|:count Commits',
        'summary' => ':commits :window',
        'windows' => [
            'day' => 'in den letzten 24 Stunden',
            'week' => 'in den letzten 7 Tagen',
            'month' => 'in den letzten 30 Tagen',
            'year' => 'im letzten Jahr',
        ],
        'on_date' => ':commits am :date',
        'between_today' => ':commits heute zwischen :start und :end',
        'between_yesterday' => ':commits gestern zwischen :start und :end',
        'month_day' => ':day. :month',
        'date_with_year' => ':day. :month :year',
        'date_with_weekday' => ':weekday, :day. :month',
        'weekday_day' => ':weekday, :day.',
        'less' => 'Weniger',
        'more' => 'Mehr',
        'months' => [
            'jan' => 'Jan.',
            'feb' => 'Feb.',
            'mar' => 'März',
            'apr' => 'Apr.',
            'may' => 'Mai',
            'jun' => 'Juni',
            'jul' => 'Juli',
            'aug' => 'Aug.',
            'sep' => 'Sept.',
            'oct' => 'Okt.',
            'nov' => 'Nov.',
            'dec' => 'Dez.',
        ],
        'weekdays' => [
            'sun' => 'So',
            'mon' => 'Mo',
            'tue' => 'Di',
            'wed' => 'Mi',
            'thu' => 'Do',
            'fri' => 'Fr',
            'sat' => 'Sa',
        ],
    ],

    'streaks' => [
        'longest' => 'längste Serie',
        'days' => 'Tag|Tage',
        'current' => 'aktuell',
    ],

    'embed' => [
        'title' => 'Aktivitätsgraphen einbetten',
        'description' => 'Dein Commit-Graph als SVG für die README deines GitHub-Profils oder überall, wo Markdown gerendert wird. Er zeigt nur freigegebene Commits und wird etwa einmal pro Stunde aktualisiert.',
        'theme_label' => 'Graph-Design',
        'themes' => [
            'light' => 'Hell',
            'dark' => 'Dunkel',
            'auto' => 'Auto',
        ],
        'theme_hints' => [
            'light' => 'Immer hell, egal, was Lesende bevorzugen.',
            'dark' => 'Immer dunkel, egal, was Lesende bevorzugen.',
            'auto' => 'Folgt dem Farbschema der Person, die gerade liest.',
        ],
        'preview_alt' => 'Commit-Aktivitätsgraph von @:username',
        'copy' => 'Markdown kopieren',
        'copied' => 'Kopiert',
        'copied_toast' => 'Markdown kopiert. Füge es in deine README ein.',
        'copy_failed' => 'fatal: Markdown konnte nicht kopiert werden',
    ],

    'live' => [
        'heading' => 'Schau uns live beim Bauen zu',
        'player_title' => 'Schau uns live auf YouTube zu, wie wir /dev/null bauen',
    ],
];
