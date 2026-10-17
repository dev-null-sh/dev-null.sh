<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => 'commit|commits',
        'following' => 'abonnements',
        'followers' => 'abonné|abonnés',
    ],

    'follows_you' => 'Te suit',
    'muted' => 'Masqué',

    'follow' => [
        'follow' => 'Suivre',
        'following' => 'Abonné',
        'unfollow' => 'Ne plus suivre',
        'follow_label' => 'Suivre @:username',
        'unfollow_label' => 'Ne plus suivre @:username',
    ],

    'profile' => [
        'edit_on_github' => 'Modifier sur GitHub',
        'view_achievements' => 'Voir les succès et le rang Unix',
        'sponsor' => 'Sponsoriser sur GitHub',
        'joined' => 'Membre depuis :date',
        'pasteis_received' => 'pastel reçu|pastéis reçus',
        'feed_label' => 'Fil du profil',
        'tabs' => [
            'commits' => 'Commits',
            'replies' => 'Réponses',
        ],
        'end' => [
            'own_first_reply' => "C'était ta première réponse.",
            'first_reply' => "C'est la première réponse de @:username.",
            'own_first_commit' => "C'était ton premier commit. Tout le monde commence quelque part.",
            'root_commit' => "C'est le commit racine de @:username.",
        ],
        'empty' => [
            'replies_command' => 'fatal: aucune réponse pour le moment',
            'own_replies' => 'Tes réponses apparaissent ici. Rejoins une conversation depuis ton log.',
            'replies' => "@:username n'a encore répondu à rien.",
            'own_commits_command' => "fatal: tu n'as encore aucun commit",
            'own_commits' => 'Tes commits apparaissent ici. Fais ton premier depuis ton log.',
            'commits_command' => 'fatal: aucun commit pour le moment',
            'commits' => "@:username n'a encore rien commité.",
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'Abonnements',
            'followers' => 'Abonnés',
        ],
        'tabs_label' => 'Relations',
        'back' => 'Retour à @:username',
        'empty_command' => [
            'following' => 'fatal: aucun abonnement pour le moment',
            'followers' => 'fatal: aucun abonné pour le moment',
        ],
        'empty' => [
            'following' => '@:username ne suit encore personne.',
            'followers' => 'Personne ne suit encore @:username.',
        ],
    ],

    'saved' => [
        'title' => 'Enregistrés',
        'only_you' => 'Toi seul',
        'description' => 'Les commits auxquels tu veux revenir. Les plus récemment enregistrés en premier.',
        'end' => 'Tu as atteint la fin de tes commits enregistrés.',
        'empty_command' => 'fatal: aucun commit enregistré pour le moment',
        'empty' => 'Enregistre un commit pour le retrouver ici. Tes commits enregistrés sont privés.',
        'explore' => 'Explorer les tendances',
    ],

    'preview' => [
        'about' => 'À propos de @:username',
        'blocked' => 'Tu as bloqué @:username.',
        'unavailable' => "Ce profil n'est pas disponible.",
    ],

    'safety' => [
        'more_options' => "Plus d'options pour @:username",
        'mute' => 'Masquer @:username',
        'unmute' => 'Ne plus masquer @:username',
        'block' => 'Bloquer @:username',
        'unblock' => 'Débloquer @:username',
        'block_title' => 'Bloquer @:username ?',
        'block_description' => "Vous ne vous suivrez plus. Aucun de vous deux ne pourra suivre l'autre, lui répondre, lui mettre une étoile ni voir ses commits. Cette personne ne sera pas prévenue, mais verra ton profil comme indisponible. Tu peux la débloquer à tout moment depuis tes paramètres.",
        'cancel' => 'Annuler',
        'confirm_block' => 'Bloquer',
    ],

    'achievements' => [
        'title' => 'Succès de développeur',
        'label' => 'Succès de développeur',
        'level' => 'Niv. :level · :rank',
        'summary' => '@:username · :xp XP · :unlocked/:total badges débloqués',
        'unlocked' => ':unlocked / :total débloqués',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':xp XP avant :rank',
        'max_rank' => 'Rang maximal (UID 0)',
        'badges_tab' => 'Badges (:count)',
        'ranks_tab' => 'Rangs Unix (:count)',
        'current' => 'ACTUEL',
        'achieved' => '✓ Obtenu',
        'requires' => 'Nécessite :xp XP',
        'formula' => 'XP : 10/commit · 15/jour de série · 5/étoile reçue · 5/réponse · 2/étoile donnée',
    ],

    'activity' => [
        'heading' => 'Activité de commits',
        'no_commits_yet' => 'Aucun commit pour le moment',
        'no_commits' => 'Aucun commit',
        'commit_count' => ':count commit|:count commits',
        'summary' => ':commits :window',
        'windows' => [
            'day' => 'sur les dernières 24 heures',
            'week' => 'sur les 7 derniers jours',
            'month' => 'sur les 30 derniers jours',
            'year' => 'sur la dernière année',
        ],
        'on_date' => ':commits le :date',
        'between_today' => ":commits aujourd'hui entre :start et :end",
        'between_yesterday' => ':commits hier entre :start et :end',
        'month_day' => ':day :month',
        'date_with_year' => ':day :month :year',
        'date_with_weekday' => ':weekday :day :month',
        'weekday_day' => ':weekday :day',
        'less' => 'Moins',
        'more' => 'Plus',
        'months' => [
            'jan' => 'janv.',
            'feb' => 'févr.',
            'mar' => 'mars',
            'apr' => 'avr.',
            'may' => 'mai',
            'jun' => 'juin',
            'jul' => 'juil.',
            'aug' => 'août',
            'sep' => 'sept.',
            'oct' => 'oct.',
            'nov' => 'nov.',
            'dec' => 'déc.',
        ],
        'weekdays' => [
            'sun' => 'dim.',
            'mon' => 'lun.',
            'tue' => 'mar.',
            'wed' => 'mer.',
            'thu' => 'jeu.',
            'fri' => 'ven.',
            'sat' => 'sam.',
        ],
    ],

    'streaks' => [
        'longest' => 'plus longue série',
        'days' => 'jour|jours',
        'current' => 'actuelle',
    ],

    'embed' => [
        'title' => "Intègre ton graphique d'activité",
        'description' => "Ton graphique de commits en SVG pour le README de ton profil GitHub, ou partout où le Markdown s'affiche. Il ne montre que les commits approuvés et se met à jour environ une fois par heure.",
        'theme_label' => 'Thème du graphique',
        'themes' => [
            'light' => 'Clair',
            'dark' => 'Sombre',
            'auto' => 'Auto',
        ],
        'theme_hints' => [
            'light' => 'Toujours clair, quelle que soit la préférence du lecteur.',
            'dark' => 'Toujours sombre, quelle que soit la préférence du lecteur.',
            'auto' => 'Suit le thème de couleur de la personne qui lit.',
        ],
        'preview_alt' => "Graphique d'activité de commits de @:username",
        'copy' => 'Copier le Markdown',
        'copied' => 'Copié',
        'copied_toast' => 'Markdown copié. Colle-le dans ton README.',
        'copy_failed' => 'fatal: impossible de copier le Markdown',
    ],

    'live' => [
        'heading' => 'Regarde-nous le construire en direct',
        'player_title' => 'Regarde-nous construire /dev/null en direct sur YouTube',
    ],
];
