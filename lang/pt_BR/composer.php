<?php

declare(strict_types=1);

return [
    'body' => 'Texto',
    'placeholder' => 'O que você entregou?',
    'poll_placeholder' => 'Faça uma pergunta',
    'commit' => 'Commitar',
    'committing' => 'Commitando…',
    'commit_title' => 'Commitar (:keys)',

    'dialog' => [
        'title' => 'Escrever um commit',
        'description' => 'Seu commit entra no seu log assim que for enviado.',
    ],

    'guest' => [
        'title' => 'Uma rede social para devs.',
        'description' => 'Todo mundo aqui entrou com o GitHub e commitou texto puro. Sua vez.',
        'sign_in_to_commit' => 'Entre com o GitHub para commitar',
        'profile' => 'Seu perfil vem do GitHub. Nada para preencher.',
        'sign_in' => 'Entrar com o GitHub',
    ],

    'schedule' => [
        'button' => 'Agendar',
        'submit_at' => 'Enviar em',
        'commit' => 'Agendar commit',
        'scheduling' => 'Agendando…',
        'commit_title' => 'Agendar commit (:keys)',
        'nonexistent_time' => 'Este horário não existe no seu fuso horário. Escolha outro.',
        'help_admin' => ':timezone. Em até 30 dias. Privado até o envio; seu commit será aprovado automaticamente.',
        'help_review' => ':timezone. Em até 30 dias. Privado até o envio; depois vai para revisão, sem publicação automática.',
        'scheduled_commits' => 'Commits agendados →',
        'images_unsupported' => 'Ainda não dá para agendar imagens. Remova-as para agendar este commit.',
        'images_unsupported_title' => 'Ainda não dá para agendar imagens',
        'polls_unsupported' => 'Ainda não dá para agendar enquetes',
    ],

    'poll' => [
        'add' => 'Adicionar enquete',
        'images_or_poll' => 'Um commit pode ter imagens ou uma enquete, não os dois',
        'legend' => 'Enquete',
        'option' => 'Opção :number',
        'remove_option' => 'Remover opção :number',
        'remove_option_title' => 'Remover opção',
        'add_option' => 'Adicionar opção',
        'length' => 'Duração da enquete',
        'remove' => 'Remover enquete',
        'hours' => ':count hora|:count horas',
        'days' => ':count dia|:count dias',
    ],

    'images' => [
        'invalid' => 'Apenas imagens JPEG, PNG, GIF ou WebP de até 10 MB.',
        'too_many' => 'Até :max imagens por commit.',
        'attach' => 'Anexar imagens',
        'attach_title' => 'Anexar até :max imagens',
        'attached' => 'Imagens anexadas',
        'attached_alt' => 'Imagem anexada :number',
        'remove' => 'Remover imagem :number',
        'edit_description' => 'Editar descrição da imagem :number',
        'add_description' => 'Adicionar descrição à imagem :number',
        'description_label' => 'Descrição da imagem :number',
        'description_placeholder' => 'Descreva a imagem para quem não consegue vê-la',
        'done' => 'Pronto',
    ],

    'preview' => [
        'label' => 'Prévia',
        'title' => 'Prévia (:keys)',
        'empty' => 'Nada para pré-visualizar ainda.',
        'link_card' => 'Vira um card de link depois que você commitar.',
    ],

    'link_card' => [
        'title' => 'Escolha qual link ganha um card',
        'label' => 'Card de link',
        'sr_label' => 'Card de link:',
        'none' => 'Sem card',
    ],

    'counter' => [
        'remaining' => 'Resta :count caractere|Restam :count caracteres',
        'fold_hint' => 'Os feeds mostram os primeiros :length caracteres ou :lines linhas e depois “Mostrar mais”.',
    ],

    'stash' => [
        'restored' => 'Seu rascunho não enviado foi restaurado',
        'drop_title' => 'Descartar o rascunho restaurado',
    ],

    'mentions' => [
        'emoji_label' => 'Inserir um emoji',
        'label' => 'Mencionar um dev',
        'following' => 'Pessoas que você segue',
        'searching' => 'Buscando…',
        'no_match' => "fatal: nenhum dev corresponde a ':query'",
    ],

    'secrets' => [
        'warning' => 'Aviso de texto sensível',
        'detected' => 'Possível segredo detectado|Possíveis segredos detectados',
        'checked_locally' => 'Verificado no seu dispositivo. Este rascunho não será salvo enquanto tiver um segredo detectado.',
        'select' => 'Selecionar possível segredo',
        'select_numbered' => 'Selecionar possível segredo :number',
        'remove_detected' => 'Remover segredos detectados',
        'confirm_title' => 'Publicar possíveis segredos?',
        'confirm_description' => 'Este texto pode conter credenciais. Remova-as antes de publicar ou continue só se forem exemplos seguros.',
        'keep_editing' => 'Continuar editando',
        'remove' => 'Remover segredos',
        'publish_anyway' => 'Publicar mesmo assim',
    ],

    'quote' => [
        'button' => 'Citar',
        'button_title' => 'Citar (:keys)',
        'unsent' => 'Você tem uma citação não enviada aqui',
        'title' => 'Citar :handle',
        'description' => 'Escreva um commit seu. Este commit fica incorporado logo abaixo.',
        'label' => 'Seu commit',
        'placeholder' => 'Dê a sua opinião',
        'cancel' => 'Cancelar',
        'submit' => 'Citar',
        'quoting' => 'Citando…',
    ],
];
