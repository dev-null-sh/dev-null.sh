<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Abbrechen',
        'delete' => 'Löschen',
        'more' => 'Weitere Aktionen',
        'reply' => 'Antworten',
    ],

    'status' => [
        'pending' => 'ausstehend',
        'approved' => 'freigegeben',
        'rejected' => 'abgelehnt',
    ],

    'card' => [
        'pending_title' => 'Nur du siehst diesen Commit, bis er freigegeben ist.',
        'rejected_title' => 'Dieser Commit wurde abgelehnt. Nur du kannst ihn sehen.',
        'commit_title' => 'Commit :id',
        'delete' => 'Commit löschen',
        'replying_to' => 'Antwort an @:username',
        'replying_to_deleted' => 'Antwort an [deleted]',
        'quoting' => 'zitiert @:username',
        'quoting_deleted' => 'zitiert [deleted]',
        'quoted' => 'zitiert',
        'confirm_delete' => 'Löschen bestätigen',
        'confirm_delete_message' => 'Diesen Commit löschen? Du kannst es 30 Sekunden lang rückgängig machen.',
        'tombstone' => 'Dieser Commit wurde gelöscht.',
    ],

    'thread' => [
        'unsent_reply' => 'Du hast hier eine nicht gesendete Antwort',
        'show_replies' => 'Antworten anzeigen',
        'hide_replies' => 'Antworten ausblenden',
        'replies' => ':count Antwort|:count Antworten',
        'loading_replies' => 'Antworten werden geladen',
        'loading_replies_ellipsis' => 'Antworten werden geladen…',
        'show_more' => 'Mehr anzeigen',
        'show_more_to' => 'Weitere Antworten an',
        'delete' => 'Antwort löschen',
        'delete_failed' => 'Deine Antwort konnte nicht gelöscht werden. Versuch es noch mal.',
        'confirm_delete_message' => 'Diese Antwort löschen? Du kannst es 30 Sekunden lang rückgängig machen.',
        'reply_failed' => 'Deine Antwort konnte nicht gesendet werden. Versuch es noch mal.',
        'parent_unavailable' => 'Auf diesen Commit kann nicht mehr geantwortet werden.',
        'reply_to' => 'Antwort an @:username',
        'replying' => 'Wird gesendet…',
    ],

    'feed' => [
        'pinned_commit' => 'Angepinnter Commit',
        'pinned' => 'Angepinnt',
        'latest_commits' => 'Neueste Commits',
        'just_now' => 'Gerade eben',
        'top_commits' => 'Top-Commits',
        'loading_more' => 'Weitere Commits werden geladen',
        'today' => 'Heute',
        'yesterday' => 'Gestern',
        'last_visit_label' => 'Die Commits darüber sind seit deinem letzten Besuch neu',
        'last_visit' => 'letzter Besuch',
    ],

    'body' => [
        'show_more' => 'Mehr anzeigen',
    ],

    'images' => [
        'attached' => 'Angehängtes Bild',
        'attached_numbered' => 'Angehängtes Bild :position von :count',
    ],

    'viewer' => [
        'hide_commit' => 'Commit ausblenden',
        'show_commit' => 'Commit anzeigen',
        'hide_description' => 'Bildbeschreibung ausblenden',
        'show_description' => 'Bildbeschreibung anzeigen',
        'commit' => 'Commit',
        'previous' => 'Zurück',
        'next' => 'Weiter',
        'close' => 'Schließen',
        'slide' => 'Folie',
        'carousel' => 'Karussell',
        'lightbox' => 'Lightbox',
        'photo_gallery' => 'Fotogalerie',
        'slide_position' => ':index von :total',
        'zoom_in' => 'Vergrößern',
        'zoom_out' => 'Verkleinern',
        'download' => 'Herunterladen',
    ],

    'poll' => [
        'vote_failed' => 'Deine Stimme konnte nicht gespeichert werden. Versuch es noch mal.',
        'votes' => ':count Stimme|:count Stimmen',
        'opens_once_approved' => 'Abstimmung startet nach der Freigabe',
        'closed' => 'Umfrage beendet',
        'final_results' => 'Endergebnis',
        'time_left' => 'noch :time',
        'options' => 'Umfrageoptionen',
        'results' => 'Umfrageergebnisse',
        'vote' => 'Abstimmen',
        'voting' => 'Wird gesendet…',
        'your_vote' => ', deine Stimme',
    ],

    'quote' => [
        'quoting' => 'Zitiert',
        'unavailable' => 'Dieser Commit ist nicht verfügbar.',
    ],

    'report' => [
        'title' => 'Commit melden',
        'description' => 'Deine Meldung ist privat. Nur Moderatoren können sie prüfen. Durch die Meldung wird der Commit nicht ausgeblendet.',
        'too_many' => 'Zu viele Meldungen. Warte eine Minute und versuch es dann noch mal.',
        'unavailable' => 'Dieser Commit kann nicht mehr gemeldet werden.',
        'network_error' => 'Deine Meldung konnte nicht gesendet werden. Prüfe deine Verbindung und versuch es noch mal.',
        'reason_legend' => 'Warum meldest du diesen Commit?',
        'reasons' => [
            'spam' => 'Spam oder irreführende Links',
            'harassment' => 'Belästigung oder Hass',
            'unsafe' => 'Schädliche oder private Informationen',
            'other' => 'Etwas anderes',
        ],
        'details' => 'Details',
        'optional' => '(optional)',
        'details_hint' => 'Gib den Moderatoren mehr Kontext. Bis zu 500 Zeichen.',
        'sending' => 'Wird gesendet…',
        'send' => 'Meldung senden',
    ],

    'bookmark' => [
        'failed' => 'Deine gespeicherten Commits konnten nicht aktualisiert werden. Versuch es noch mal.',
        'save_commit' => 'Commit speichern',
        'unsave_commit' => 'Nicht mehr speichern',
        'saved' => 'Gespeichert',
        'save_privately' => 'Commit privat speichern',
    ],

    'copy_link' => [
        'copy' => 'Link kopieren',
        'copied' => 'Link zu :hash kopiert',
        'failed' => 'Link zu :hash konnte nicht kopiert werden',
    ],

    'star' => [
        'star' => 'Stern geben',
        'unstar' => 'Stern entfernen',
        'star_commit' => 'Commit einen Stern geben',
        'unstar_commit' => 'Stern vom Commit entfernen',
        'star_commit_count' => 'Commit einen Stern geben, :count Stern|Commit einen Stern geben, :count Sterne',
        'unstar_commit_count' => 'Stern vom Commit entfernen, :count Stern|Stern vom Commit entfernen, :count Sterne',
        'no_stars' => 'Noch keine Sterne',
        'stars' => ':count Stern|:count Sterne',
    ],

    'undo' => [
        'expired' => 'Zeit zum Rückgängigmachen abgelaufen.',
        'restored' => 'Commit wiederhergestellt.',
        'restore_failed' => 'Commit konnte nicht wiederhergestellt werden.',
        'deleted' => 'Commit gelöscht.',
        'time_to_undo' => 'Noch :time zum Rückgängigmachen',
        'availability' => 'Rückgängigmachen ist nach dem Löschen 30 Sekunden lang möglich.',
        'restoring' => 'Wird wiederhergestellt…',
        'undo' => 'Rückgängig',
    ],

    'pastel' => [
        'exhausted' => 'deine pastéis sind alle. warte bis morgen.',
        'count' => ':count Pastel|:count Pastéis',
        'award_failed' => 'Pastel konnte nicht vergeben werden. Versuch es noch mal.',
        'give_label' => 'Pastel geben, :pasteis, heute noch :remaining übrig',
        'give_title' => 'Pastel geben · heute noch :remaining übrig · Reset um 00:00 UTC',
    ],

    'pin' => [
        'pin_to_profile' => 'Im Profil anpinnen',
        'unpin_from_profile' => 'Vom Profil lösen',
        'pinned' => 'Commit in deinem Profil angepinnt.',
        'unpinned' => 'Commit gelöst.',
        'failed' => 'Dein angepinnter Commit konnte nicht aktualisiert werden.',
        'saving' => 'Wird gespeichert…',
        'pin' => 'Anpinnen',
        'unpin' => 'Lösen',
    ],

    'embed' => [
        'play' => ':title von :provider abspielen',
        'player' => ':provider-Player',
    ],

    'code' => [
        'copy' => 'Code kopieren',
        'copied' => 'Code in die Zwischenablage kopiert',
        'copy_failed' => 'Code konnte nicht kopiert werden. Markiere ihn und kopiere ihn manuell.',
        'wrap_lines' => 'Zeilen umbrechen',
    ],

    'copyable' => [
        'copy' => ':label kopieren',
        'copied' => ':label kopiert',
        'failed' => ':label konnte nicht kopiert werden',
    ],

    'show' => [
        'title' => ':name: „:excerpt“',
        'back' => 'Zurück',
        'heading' => 'Commit',
    ],
];
