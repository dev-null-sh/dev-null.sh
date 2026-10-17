<?php

declare(strict_types=1);

return [
    'body' => 'Contenu',
    'placeholder' => "Qu'as-tu livré ?",
    'poll_placeholder' => 'Pose une question',
    'commit' => 'Commit',
    'committing' => 'Commit en cours…',
    'commit_title' => 'Commit (:keys)',

    'dialog' => [
        'title' => 'Écrire un commit',
        'description' => 'Ton commit est ajouté à ton log dès son envoi.',
    ],

    'guest' => [
        'title' => 'Un réseau social pour les développeurs.',
        'description' => "Tout le monde ci-dessous s'est connecté avec GitHub et a commité du texte brut. À ton tour.",
        'sign_in_to_commit' => 'Connecte-toi avec GitHub pour commiter',
        'profile' => 'Ton profil vient de GitHub. Rien à remplir.',
        'sign_in' => 'Se connecter avec GitHub',
    ],

    'schedule' => [
        'button' => 'Programmer',
        'submit_at' => 'Envoyer le',
        'commit' => 'Programmer le commit',
        'scheduling' => 'Programmation…',
        'commit_title' => 'Programmer le commit (:keys)',
        'nonexistent_time' => "Cette heure n'existe pas dans ton fuseau horaire. Choisis une autre heure.",
        'help_admin' => ":timezone. Dans les 30 jours. Privé jusqu'à l'envoi ; ton commit sera approuvé automatiquement.",
        'help_review' => ":timezone. Dans les 30 jours. Privé jusqu'à l'envoi ; puis soumis à validation, pas publié automatiquement.",
        'scheduled_commits' => 'Commits programmés →',
    ],

    'poll' => [
        'add' => 'Ajouter un sondage',
        'images_or_poll' => 'Un commit peut avoir des images ou un sondage, pas les deux',
        'legend' => 'Sondage',
        'option' => 'Option :number',
        'remove_option' => "Retirer l'option :number",
        'remove_option_title' => "Retirer l'option",
        'add_option' => 'Ajouter une option',
        'length' => 'Durée du sondage',
        'remove' => 'Retirer le sondage',
        'hours' => ':count heure|:count heures',
        'days' => ':count jour|:count jours',
    ],

    'images' => [
        'invalid' => 'Uniquement des images JPEG, PNG ou WebP jusqu’à 10 Mo.',
        'drop_to_attach' => 'Dépose les images pour les joindre',
        'too_many' => "Jusqu'à :max images par commit.",
        'attach' => 'Joindre des images',
        'attach_title' => "Joindre jusqu'à :max images",
        'attached' => 'Images jointes',
        'attached_alt' => 'Image jointe :number',
        'remove' => "Retirer l'image :number",
        'edit_description' => "Modifier la description de l'image :number",
        'add_description' => "Ajouter une description à l'image :number",
        'description_label' => "Description de l'image :number",
        'description_placeholder' => "Décris l'image pour les personnes qui ne peuvent pas la voir",
        'done' => 'Terminé',
    ],

    'preview' => [
        'label' => 'Aperçu',
        'title' => 'Aperçu (:keys)',
        'empty' => "Rien à prévisualiser pour l'instant.",
        'link_card' => 'Se transforme en carte de lien une fois commité.',
    ],

    'link_card' => [
        'title' => 'Choisis quel lien aura une carte',
        'label' => 'Carte de lien',
        'sr_label' => 'Carte de lien :',
        'none' => 'Pas de carte',
    ],

    'counter' => [
        'remaining' => ':count caractère restant|:count caractères restants',
        'fold_hint' => 'Les fils affichent les :length premiers caractères ou :lines lignes, puis « Afficher plus ».',
    ],

    'stash' => [
        'restored' => 'Ton brouillon non envoyé a été restauré',
        'drop_title' => 'Supprimer le brouillon restauré',
    ],

    'mentions' => [
        'emoji_label' => 'Insérer un emoji',
        'label' => 'Mentionner un développeur',
        'following' => 'Personnes que tu suis',
        'searching' => 'Recherche…',
        'no_match' => "fatal: aucun développeur ne correspond à ':query'",
    ],

    'secrets' => [
        'warning' => 'Alerte de texte sensible',
        'detected' => 'Secret potentiel détecté|Secrets potentiels détectés',
        'checked_locally' => "Vérifié sur ton appareil. Ce brouillon ne sera pas enregistré tant qu'il contient un secret détecté.",
        'select' => 'Sélectionner le secret potentiel',
        'select_numbered' => 'Sélectionner le secret potentiel :number',
        'remove_detected' => 'Retirer les secrets détectés',
        'confirm_title' => 'Publier des secrets potentiels ?',
        'confirm_description' => "Ce texte peut contenir des identifiants. Retire-les avant de publier, ou continue seulement s'il s'agit d'exemples sans risque.",
        'keep_editing' => 'Continuer à modifier',
        'remove' => 'Retirer les secrets',
        'publish_anyway' => 'Publier quand même',
    ],

    'quote' => [
        'button' => 'Citer',
        'button_title' => 'Citer (:keys)',
        'unsent' => 'Tu as une citation non envoyée ici',
        'title' => 'Citer :handle',
        'description' => 'Écris ton propre commit. Ce commit sera intégré en dessous.',
        'label' => 'Ton commit',
        'placeholder' => 'Ajoute ton avis',
        'cancel' => 'Annuler',
        'submit' => 'Citer',
        'quoting' => 'Citation…',
    ],
];
