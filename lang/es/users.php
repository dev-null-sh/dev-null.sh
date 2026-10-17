<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => 'commit|commits',
        'following' => 'siguiendo',
        'followers' => 'seguidor|seguidores',
    ],

    'follows_you' => 'Te sigue',
    'muted' => 'Silenciado',

    'follow' => [
        'follow' => 'Seguir',
        'following' => 'Siguiendo',
        'unfollow' => 'Dejar de seguir',
        'follow_label' => 'Seguir a @:username',
        'unfollow_label' => 'Dejar de seguir a @:username',
    ],

    'profile' => [
        'edit_on_github' => 'Editar en GitHub',
        'view_achievements' => 'Ver logros y rango Unix',
        'sponsor' => 'Patrocinar en GitHub',
        'joined' => 'Se unió en :date',
        'pasteis_received' => 'pastel recibido|pastéis recibidos',
        'feed_label' => 'Feed del perfil',
        'tabs' => [
            'commits' => 'Commits',
            'replies' => 'Respuestas',
        ],
        'end' => [
            'own_first_reply' => 'Esa fue tu primera respuesta.',
            'first_reply' => 'Esa es la primera respuesta de @:username.',
            'own_first_commit' => 'Ese fue tu primer commit. Todos empiezan en algún lugar.',
            'root_commit' => 'Ese es el commit raíz de @:username.',
        ],
        'empty' => [
            'replies_command' => 'fatal: aún no hay respuestas',
            'own_replies' => 'Tus respuestas aparecen aquí. Únete a una conversación desde tu log.',
            'replies' => '@:username aún no ha respondido a nada.',
            'own_commits_command' => 'fatal: aún no tienes commits',
            'own_commits' => 'Tus commits aparecen aquí. Haz el primero desde tu log.',
            'commits_command' => 'fatal: aún no hay commits',
            'commits' => '@:username aún no ha hecho ningún commit.',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'Siguiendo',
            'followers' => 'Seguidores',
        ],
        'tabs_label' => 'Conexiones',
        'back' => 'Volver a @:username',
        'empty_command' => [
            'following' => 'fatal: aún no sigue a nadie',
            'followers' => 'fatal: aún no hay seguidores',
        ],
        'empty' => [
            'following' => '@:username aún no sigue a nadie.',
            'followers' => 'Nadie sigue a @:username todavía.',
        ],
    ],

    'saved' => [
        'title' => 'Guardados',
        'only_you' => 'Solo tú',
        'description' => 'Commits a los que quieres volver. Los guardados más recientemente primero.',
        'end' => 'Llegaste al final de tus commits guardados.',
        'empty_command' => 'fatal: aún no hay commits guardados',
        'empty' => 'Guarda un commit para encontrarlo aquí. Tus commits guardados son privados.',
        'explore' => 'Explorar Tendencias',
    ],

    'preview' => [
        'about' => 'Acerca de @:username',
        'blocked' => 'Bloqueaste a @:username.',
        'unavailable' => 'Este perfil no está disponible.',
    ],

    'safety' => [
        'more_options' => 'Más opciones para @:username',
        'mute' => 'Silenciar a @:username',
        'unmute' => 'Dejar de silenciar a @:username',
        'block' => 'Bloquear a @:username',
        'unblock' => 'Desbloquear a @:username',
        'block_title' => '¿Bloquear a @:username?',
        'block_description' => 'Dejarán de seguirse mutuamente. Ninguno de los dos podrá seguir, responder ni dar estrella al otro, ni ver los commits del otro. No se le avisará, pero verá tu perfil como no disponible. Puedes desbloquearlo cuando quieras desde tus ajustes.',
        'cancel' => 'Cancelar',
        'confirm_block' => 'Bloquear',
    ],

    'achievements' => [
        'title' => 'Logros de desarrollador',
        'label' => 'Logros de desarrollador',
        'level' => 'Nv. :level · :rank',
        'summary' => '@:username · :xp XP · :unlocked/:total insignias desbloqueadas',
        'unlocked' => ':unlocked / :total desbloqueadas',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':xp XP para :rank',
        'max_rank' => 'Rango máximo (UID 0)',
        'badges_tab' => 'Insignias (:count)',
        'ranks_tab' => 'Rangos Unix (:count)',
        'current' => 'ACTUAL',
        'achieved' => '✓ Logrado',
        'requires' => 'Requiere :xp XP',
        'formula' => 'XP: 10/commit · 15/día de racha · 5/estrella recibida · 5/respuesta · 2/estrella dada',
    ],

    'activity' => [
        'heading' => 'Actividad de commits',
        'no_commits_yet' => 'Aún no hay commits',
        'no_commits' => 'Sin commits',
        'commit_count' => ':count commit|:count commits',
        'summary' => ':commits :window',
        'windows' => [
            'day' => 'en las últimas 24 horas',
            'week' => 'en los últimos 7 días',
            'month' => 'en los últimos 30 días',
            'year' => 'en el último año',
        ],
        'on_date' => ':commits el :date',
        'between_today' => ':commits hoy entre :start y :end',
        'between_yesterday' => ':commits ayer entre :start y :end',
        'month_day' => ':day :month',
        'date_with_year' => ':day :month :year',
        'date_with_weekday' => ':weekday, :day :month',
        'weekday_day' => ':weekday :day',
        'less' => 'Menos',
        'more' => 'Más',
        'months' => [
            'jan' => 'ene',
            'feb' => 'feb',
            'mar' => 'mar',
            'apr' => 'abr',
            'may' => 'may',
            'jun' => 'jun',
            'jul' => 'jul',
            'aug' => 'ago',
            'sep' => 'sep',
            'oct' => 'oct',
            'nov' => 'nov',
            'dec' => 'dic',
        ],
        'weekdays' => [
            'sun' => 'dom',
            'mon' => 'lun',
            'tue' => 'mar',
            'wed' => 'mié',
            'thu' => 'jue',
            'fri' => 'vie',
            'sat' => 'sáb',
        ],
    ],

    'streaks' => [
        'longest' => 'racha más larga',
        'days' => 'día|días',
        'current' => 'actual',
    ],

    'embed' => [
        'title' => 'Inserta tu gráfico de actividad',
        'description' => 'Tu gráfico de commits como SVG para el README de tu perfil de GitHub, o en cualquier lugar donde se renderice Markdown. Solo muestra commits aprobados y se actualiza aproximadamente una vez por hora.',
        'theme_label' => 'Tema del gráfico',
        'themes' => [
            'light' => 'Claro',
            'dark' => 'Oscuro',
            'auto' => 'Auto',
        ],
        'theme_hints' => [
            'light' => 'Siempre claro, sin importar la preferencia del lector.',
            'dark' => 'Siempre oscuro, sin importar la preferencia del lector.',
            'auto' => 'Sigue el esquema de color de quien lo esté leyendo.',
        ],
        'preview_alt' => 'Gráfico de actividad de commits de @:username',
        'copy' => 'Copiar Markdown',
        'copied' => 'Copiado',
        'copied_toast' => 'Markdown copiado. Pégalo en tu README.',
        'copy_failed' => 'fatal: no se pudo copiar el Markdown',
    ],

    'live' => [
        'heading' => 'Míranos construirlo en vivo',
        'player_title' => 'Míranos construir /dev/null en vivo en YouTube',
    ],
];
