<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Cancelar',
        'delete' => 'Excluir',
        'more' => 'Mais ações',
        'reply' => 'Responder',
    ],

    'status' => [
        'pending' => 'pendente',
        'approved' => 'aprovado',
        'rejected' => 'rejeitado',
    ],

    'card' => [
        'pending_title' => 'Só você vê este commit até ele ser aprovado.',
        'rejected_title' => 'Este commit foi rejeitado. Só você pode vê-lo.',
        'commit_title' => 'Commit :id',
        'delete' => 'Excluir commit',
        'replying_to' => 'respondendo a @:username',
        'replying_to_deleted' => 'respondendo a [deleted]',
        'confirm_delete' => 'Confirmar exclusão',
        'confirm_delete_message' => 'Excluir este commit? Você pode desfazer por 30 segundos.',
        'tombstone' => 'Este commit foi excluído.',
    ],

    'thread' => [
        'unsent_reply' => 'Você tem uma resposta não enviada aqui',
        'show_replies' => 'Mostrar respostas',
        'hide_replies' => 'Ocultar respostas',
        'replies' => ':count resposta|:count respostas',
        'loading_replies' => 'Carregando respostas',
        'loading_replies_ellipsis' => 'Carregando respostas…',
        'show_more' => 'Mostrar mais',
        'show_more_to' => 'Mais respostas a',
        'delete' => 'Excluir resposta',
        'delete_failed' => 'Não foi possível excluir sua resposta. Tente de novo.',
        'confirm_delete_message' => 'Excluir esta resposta? Você pode desfazer por 30 segundos.',
        'reply_failed' => 'Não foi possível enviar sua resposta. Tente de novo.',
        'parent_unavailable' => 'Esse commit não está mais disponível para resposta.',
        'reply_to' => 'Responder a @:username',
        'replying' => 'Respondendo…',
    ],

    'feed' => [
        'pinned_commit' => 'Commit fixado',
        'pinned' => 'Fixado',
        'latest_commits' => 'Commits recentes',
        'just_now' => 'Agora mesmo',
        'top_commits' => 'Principais commits',
        'loading_more' => 'Carregando mais commits',
        'today' => 'Hoje',
        'yesterday' => 'Ontem',
        'last_visit_label' => 'Os commits acima são novos desde a sua última visita',
        'last_visit' => 'última visita',
    ],

    'body' => [
        'show_more' => 'Mostrar mais',
    ],

    'images' => [
        'attached' => 'Imagem anexada',
        'attached_numbered' => 'Imagem anexada :position de :count',
    ],

    'viewer' => [
        'hide_commit' => 'Ocultar commit',
        'show_commit' => 'Mostrar commit',
        'hide_description' => 'Ocultar descrição da imagem',
        'show_description' => 'Mostrar descrição da imagem',
        'commit' => 'Commit',
        'previous' => 'Anterior',
        'next' => 'Próxima',
        'close' => 'Fechar',
        'slide' => 'Slide',
        'carousel' => 'Carrossel',
        'lightbox' => 'Lightbox',
        'photo_gallery' => 'Galeria de fotos',
        'slide_position' => ':index de :total',
        'zoom_in' => 'Ampliar',
        'zoom_out' => 'Reduzir',
        'download' => 'Baixar',
    ],

    'poll' => [
        'vote_failed' => 'Não foi possível registrar seu voto. Tente de novo.',
        'votes' => ':count voto|:count votos',
        'opens_once_approved' => 'A votação abre após a aprovação',
        'closed' => 'Enquete encerrada',
        'final_results' => 'Resultado final',
        'time_left' => 'faltam :time',
        'options' => 'Opções da enquete',
        'results' => 'Resultados da enquete',
        'vote' => 'Votar',
        'voting' => 'Votando…',
        'your_vote' => ', seu voto',
    ],

    'quote' => [
        'quoting' => 'Citando',
        'unavailable' => 'Este commit não está disponível.',
    ],

    'report' => [
        'title' => 'Denunciar commit',
        'description' => 'Sua denúncia é privada. Só moderadores podem analisá-la. Denunciar não oculta este commit.',
        'too_many' => 'Denúncias demais. Espere um minuto e tente de novo.',
        'unavailable' => 'Este commit não está mais disponível para denúncia.',
        'network_error' => 'Não foi possível enviar sua denúncia. Verifique sua conexão e tente de novo.',
        'reason_legend' => 'Por que você está denunciando este commit?',
        'reasons' => [
            'spam' => 'Spam ou links enganosos',
            'harassment' => 'Assédio ou discurso de ódio',
            'unsafe' => 'Informações nocivas ou privadas',
            'other' => 'Outro motivo',
        ],
        'details' => 'Detalhes',
        'optional' => '(opcional)',
        'details_hint' => 'Dê mais contexto aos moderadores. Até 500 caracteres.',
        'sending' => 'Enviando…',
        'send' => 'Enviar denúncia',
    ],

    'bookmark' => [
        'failed' => 'Não foi possível atualizar seus commits salvos. Tente de novo.',
        'save_commit' => 'Salvar commit',
        'unsave_commit' => 'Remover dos salvos',
        'saved' => 'Salvo',
        'save_privately' => 'Salvar commit de forma privada',
    ],

    'copy_link' => [
        'copy' => 'Copiar link',
        'copied' => 'Link para :hash copiado',
        'failed' => 'não foi possível copiar o link para :hash',
    ],

    'star' => [
        'star' => 'Dar estrela',
        'unstar' => 'Tirar estrela',
        'star_commit' => 'Dar estrela ao commit',
        'unstar_commit' => 'Tirar estrela do commit',
        'star_commit_count' => 'Dar estrela ao commit, :count estrela|Dar estrela ao commit, :count estrelas',
        'unstar_commit_count' => 'Tirar estrela do commit, :count estrela|Tirar estrela do commit, :count estrelas',
        'no_stars' => 'Nenhuma estrela ainda',
        'stars' => ':count estrela|:count estrelas',
    ],

    'undo' => [
        'expired' => 'O prazo para desfazer acabou.',
        'restored' => 'Commit restaurado.',
        'restore_failed' => 'Não foi possível restaurar este commit.',
        'deleted' => 'Commit excluído.',
        'time_to_undo' => ':time para desfazer',
        'availability' => 'Dá para desfazer por 30 segundos após a exclusão.',
        'restoring' => 'Restaurando…',
        'undo' => 'Desfazer',
    ],

    'pastel' => [
        'exhausted' => 'seus pastéis acabaram. espere até amanhã.',
        'count' => ':count pastel|:count pastéis',
        'award_failed' => 'Não foi possível dar o pastel. Tente de novo.',
        'give_label' => 'Dar um pastel, :pasteis, restam :remaining hoje',
        'give_title' => 'Dar um pastel · restam :remaining hoje · renova às 00:00 UTC',
    ],

    'pin' => [
        'pin_to_profile' => 'Fixar no perfil',
        'unpin_from_profile' => 'Desafixar do perfil',
        'pinned' => 'Commit fixado no seu perfil.',
        'unpinned' => 'Commit desafixado.',
        'failed' => 'Não foi possível atualizar seu commit fixado.',
        'saving' => 'Salvando…',
        'pin' => 'Fixar',
        'unpin' => 'Desafixar',
    ],

    'embed' => [
        'play' => 'Reproduzir :title de :provider',
        'player' => 'Player do :provider',
    ],

    'code' => [
        'copy' => 'Copiar código',
        'copied' => 'Código copiado para a área de transferência',
        'copy_failed' => 'Não foi possível copiar o código. Selecione e copie manualmente.',
        'wrap_lines' => 'Quebrar linhas',
    ],

    'copyable' => [
        'copy' => 'Copiar :label',
        'copied' => ':label copiado',
        'failed' => 'não foi possível copiar :label',
    ],

    'show' => [
        'title' => ':name: “:excerpt”',
        'back' => 'Voltar',
        'heading' => 'Commit',
    ],
];
