<?php

declare(strict_types=1);

return [
    'title' => 'Paramètres',

    'tabs' => [
        'profile' => 'Profil',
        'appearance' => 'Apparence',
        'terminal' => 'Terminal',
        'safety' => 'Sécurité',
    ],

    'appearance' => [
        'title' => "Paramètres d'apparence",
        'theme' => 'Thème',
        'theme_description' => "Système suit ton système d'exploitation : clair le jour et sombre la nuit, si c'est ainsi que tu l'as réglé.",
        'light' => 'Clair',
        'dark' => 'Sombre',
        'system' => 'Système',
        'who_to_follow' => 'Qui suivre',
        'who_to_follow_description' => 'Trois personnes que tu pourrais vouloir suivre, dans la colonne de droite, ou dans ton log sur les petits écrans.',
        'show' => 'Afficher',
        'hide' => 'Masquer',
    ],

    'language' => [
        'title' => 'Langue',
        'description' => "La langue dans laquelle devnull s'affiche. Les commits restent dans la langue où ils ont été écrits.",
    ],

    'profile' => [
        'title' => 'Paramètres du profil',
        'information' => 'Informations du profil',
        'information_description' => 'Copiées depuis GitHub quand tu te connectes. Pour les modifier, change-les sur GitHub, puis synchronise-les ici.',
        'edit_on_github' => 'Modifier sur GitHub',
        'sync' => 'Synchroniser depuis GitHub',
        'view' => 'Voir le profil',
        'last_synced' => 'Dernière synchronisation',
        'last_synced_at_sign_in' => 'Synchronisé pour la dernière fois à ta connexion',
        'just_now' => "à l'instant",
        'export' => 'Exporter tes données',
        'export_description' => 'Télécharge tout ce que nous stockons sur toi : ton profil, tes commits et réponses (y compris ceux en attente, rejetés et supprimés), étoiles, commits enregistrés, abonnements, comptes masqués, blocages, notifications, empreintes de clés SSH et sessions actives.',
        'export_note' => 'Les réponses des autres personnes sont exclues. Ton export est un fichier JSON. Ton profil reste géré sur GitHub.',
        'download_json' => 'Télécharger le JSON',
    ],

    'lane' => [
        'title' => 'Couleur',
        'description' => "Ta voie sur le graphe : le rail et les nœuds à côté de tes commits, l'anneau autour de ton avatar et le point avant ton pseudo. Automatique en choisit une à partir de l'id de ton compte.",
        'auto' => 'Auto',
        'automatic' => 'Automatique (:color)',
        'names' => [
            'orange' => 'Orange',
            'green' => 'Vert',
            'violet' => 'Violet',
            'teal' => 'Bleu canard',
            'rose' => 'Rose',
            'ochre' => 'Ocre',
        ],
        'save' => 'Enregistrer la couleur',
    ],

    'pasteis' => [
        'title' => 'Mode pastéis',
        'description' => 'Un peu de reconnaissance, fraîche chaque jour. Reçois 10 pastéis à offrir aux commits des autres, un par clic. Ton quota se réinitialise à 00:00 UTC.',
        'off_note' => 'Une fois désactivé, toutes les commandes et tous les compteurs de pastéis sont masqués. Tu reçois toujours des pastéis des autres.',
        'save_failed' => "Impossible d'enregistrer le mode pastéis. Réessaie.",
    ],

    'delete_account' => [
        'title' => 'Supprimer le compte',
        'description' => "Supprime ton compte /dev/null, chaque commit et réponse que tu as publiés, et tous tes abonnements. Les réponses laissées par d'autres sous tes commits partent aussi. Les réponses à tes réponses restent, accrochées à une pierre tombale [deleted]. Ton compte GitHub n'est pas touché.",
        'confirm_title' => 'Supprimer ton compte ?',
        'confirm_description' => "Ton profil, tes commits, réponses et abonnements partent dans /dev/null, avec chaque réponse laissée par d'autres sous tes commits. Les réponses à tes réponses restent, sous une pierre tombale [deleted]. C'est irréversible. Tu pourras te reconnecter avec GitHub plus tard, mais tu repartiras de zéro.",
        'keep' => 'Garder mon compte',
    ],

    'safety' => [
        'title' => 'Paramètres de sécurité',
        'muted' => 'Masqués',
        'muted_description' => "Leurs commits et réponses n'apparaissent plus dans tes logs, fils, recherches et suggestions, et ils ne peuvent pas te notifier. Ils ne sont pas prévenus, et tu peux toujours visiter leur profil.",
        'muted_empty' => "Tu n'as masqué personne.",
        'unmute' => 'Ne plus masquer',
        'blocked' => 'Bloqués',
        'blocked_description' => "Aucun de vous deux ne peut suivre l'autre, lui répondre, lui mettre une étoile ni voir ses commits. Débloquer ne rétablit pas les abonnements.",
        'blocked_empty' => "Tu n'as bloqué personne.",
        'unblock' => 'Débloquer',
    ],

    'terminal' => [
        'title' => 'Paramètres du terminal',
        'from_terminal' => 'Commiter depuis ton terminal',
        'from_terminal_description' => 'Ni login, ni token : le script signe ton commit avec une clé SSH de ton compte GitHub, et /dev/null la vérifie avec les clés que GitHub publie pour toi.',
        'command' => 'commande',
        'reply_command' => 'commande de réponse',
        'key_command' => 'commande de clé',
        'workflow' => 'workflow',
        'from_actions' => 'Commiter depuis GitHub Actions',
        'from_actions_description' => "Publie des notes de version quand tu sors une release, ou tout ce qu'un workflow sait. Même signature, même modération, mêmes 10 commits par minute.",
        'step_generate' => 'Génère une clé réservée à la CI.',
        'step_add_key_before' => 'Ajoute',
        'step_add_key_middle' => 'à GitHub comme',
        'step_add_key_link' => 'clé de signature',
        'step_add_key_after' => '. Elle peut signer des commits /dev/null, mais jamais pousser du code ni se connecter.',
        'step_save_secret_before' => 'Enregistre',
        'step_save_secret_middle' => ', la clé privée, comme secret de dépôt nommé',
        'step_save_secret_after' => ', puis supprime les deux fichiers.',
        'step_add_workflow_before' => 'Ajoute le workflow dans',
        'step_add_workflow_after' => '.',
        'inputs' => [
            'body' => 'Le commit, de 3 à 4 000 caractères.',
            'ssh_key' => 'La clé privée, depuis un secret. Ed25519 ou RSA, sans phrase de passe.',
            'reply_to' => "Facultatif. Le hash ou le lien d'un commit auquel répondre.",
        ],
        'job_output' => "Le job affiche le lien vers ton commit, et s'il est en attente de validation. Quand /dev/null le refuse, le job échoue avec le motif.",
    ],

    'scheduled' => [
        'title' => 'Commits programmés',
        'back_to_log' => 'Retour au log',
        'private' => 'Privé',
        'intro' => "Toi seul peux voir cette liste. À l'heure prévue, chaque commit passe par le processus de validation habituel. Programmer n'est pas approuver.",
        'times_in' => 'Fuseau horaire : :timezone',
        'empty_title' => 'Aucun commit programmé',
        'empty_description' => "Choisis Programmer dans l'éditeur pour envoyer plus tard.",
        'status' => [
            'scheduled' => 'Programmé',
            'waiting' => "En attente d'envoi",
            'pending' => 'Soumis à validation',
            'approved' => 'Approuvé',
            'rejected' => 'Rejeté',
            'deleted' => 'Supprimé',
        ],
        'cancel' => 'Annuler',
        'cancel_label' => 'Annuler le commit programmé pour :time',
        'canceling' => 'Annulation…',
        'due_note' => "En attente du planificateur. Si l'envoi échoue, il est relancé automatiquement.",
        'private_note' => "Privé jusqu'à l'envoi. Tu peux annuler avant l'envoi.",
        'previous' => 'Précédent',
        'next' => 'Suivant',
    ],
];
