<?php

declare(strict_types=1);

return [
    'body' => 'Text',
    'placeholder' => 'Was hast du gebaut?',
    'poll_placeholder' => 'Stell eine Frage',
    'commit' => 'Committen',
    'committing' => 'Wird committet…',
    'commit_title' => 'Committen (:keys)',

    'dialog' => [
        'title' => 'Commit schreiben',
        'description' => 'Dein Commit landet in deinem Log, sobald er abgeschickt ist.',
    ],

    'guest' => [
        'title' => 'Ein soziales Netzwerk für Entwickler.',
        'description' => 'Alle hier haben sich mit GitHub angemeldet und reinen Text committet. Jetzt du.',
        'sign_in_to_commit' => 'Mit GitHub anmelden und committen',
        'profile' => 'Dein Profil kommt von GitHub. Nichts auszufüllen.',
        'sign_in' => 'Mit GitHub anmelden',
    ],

    'schedule' => [
        'button' => 'Planen',
        'submit_at' => 'Senden am',
        'commit' => 'Commit planen',
        'scheduling' => 'Wird geplant…',
        'commit_title' => 'Commit planen (:keys)',
        'nonexistent_time' => 'Diese Uhrzeit gibt es in deiner Zeitzone nicht. Wähle eine andere.',
        'help_admin' => ':timezone. Innerhalb von 30 Tagen. Privat bis zum Absenden; dein Commit wird automatisch freigegeben.',
        'help_review' => ':timezone. Innerhalb von 30 Tagen. Privat bis zum Absenden; danach wird er zur Prüfung eingereicht, nicht automatisch veröffentlicht.',
        'scheduled_commits' => 'Geplante Commits →',
        'images_unsupported' => 'Bilder können noch nicht geplant werden. Entferne sie, um diesen Commit zu planen.',
        'images_unsupported_title' => 'Bilder können noch nicht geplant werden',
        'polls_unsupported' => 'Umfragen können noch nicht geplant werden',
    ],

    'poll' => [
        'add' => 'Umfrage hinzufügen',
        'images_or_poll' => 'Ein Commit kann Bilder oder eine Umfrage haben, nicht beides',
        'legend' => 'Umfrage',
        'option' => 'Option :number',
        'remove_option' => 'Option :number entfernen',
        'remove_option_title' => 'Option entfernen',
        'add_option' => 'Option hinzufügen',
        'length' => 'Umfragedauer',
        'remove' => 'Umfrage entfernen',
        'hours' => ':count Stunde|:count Stunden',
        'days' => ':count Tag|:count Tage',
    ],

    'images' => [
        'invalid' => 'Nur JPEG-, PNG-, GIF- oder WebP-Bilder bis 10 MB.',
        'too_many' => 'Bis zu :max Bilder pro Commit.',
        'attach' => 'Bilder anhängen',
        'attach_title' => 'Bis zu :max Bilder anhängen',
        'attached' => 'Angehängte Bilder',
        'attached_alt' => 'Angehängtes Bild :number',
        'remove' => 'Bild :number entfernen',
        'edit_description' => 'Beschreibung von Bild :number bearbeiten',
        'add_description' => 'Beschreibung zu Bild :number hinzufügen',
        'description_label' => 'Beschreibung von Bild :number',
        'description_placeholder' => 'Beschreibe das Bild für Menschen, die es nicht sehen können',
        'done' => 'Fertig',
    ],

    'preview' => [
        'label' => 'Vorschau',
        'title' => 'Vorschau (:keys)',
        'empty' => 'Noch nichts für die Vorschau.',
        'link_card' => 'Wird nach dem Commit zu einer Link-Karte.',
    ],

    'link_card' => [
        'title' => 'Wähle, welcher Link eine Karte bekommt',
        'label' => 'Link-Karte',
        'sr_label' => 'Link-Karte:',
        'none' => 'Keine Karte',
    ],

    'counter' => [
        'remaining' => 'Noch :count Zeichen|Noch :count Zeichen',
        'fold_hint' => 'Feeds zeigen die ersten :length Zeichen oder :lines Zeilen, dann „Mehr anzeigen“.',
    ],

    'stash' => [
        'restored' => 'Dein nicht gesendeter Entwurf wurde wiederhergestellt',
        'drop_title' => 'Wiederhergestellten Entwurf verwerfen',
    ],

    'mentions' => [
        'emoji_label' => 'Emoji einfügen',
        'label' => 'Entwickler erwähnen',
        'following' => 'Leute, denen du folgst',
        'searching' => 'Suche…',
        'no_match' => "fatal: keine Entwickler passen zu ':query'",
    ],

    'secrets' => [
        'warning' => 'Warnung vor sensiblem Text',
        'detected' => 'Mögliches Secret erkannt|Mögliche Secrets erkannt',
        'checked_locally' => 'Auf deinem Gerät geprüft. Dieser Entwurf wird nicht gespeichert, solange er ein erkanntes Secret enthält.',
        'select' => 'Mögliches Secret auswählen',
        'select_numbered' => 'Mögliches Secret :number auswählen',
        'remove_detected' => 'Erkannte Secrets entfernen',
        'confirm_title' => 'Mögliche Secrets veröffentlichen?',
        'confirm_description' => 'Dieser Text enthält eventuell Zugangsdaten. Entferne sie vor dem Veröffentlichen oder fahre nur fort, wenn es harmlose Beispiele sind.',
        'keep_editing' => 'Weiter bearbeiten',
        'remove' => 'Secrets entfernen',
        'publish_anyway' => 'Trotzdem veröffentlichen',
    ],

    'quote' => [
        'button' => 'Zitieren',
        'button_title' => 'Zitieren (:keys)',
        'unsent' => 'Du hast hier ein nicht gesendetes Zitat',
        'title' => ':handle zitieren',
        'description' => 'Schreib einen eigenen Commit. Dieser Commit wird darunter eingebettet.',
        'label' => 'Dein Commit',
        'placeholder' => 'Was meinst du dazu?',
        'cancel' => 'Abbrechen',
        'submit' => 'Zitieren',
        'quoting' => 'Wird zitiert…',
    ],
];
