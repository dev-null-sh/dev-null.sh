<?php

declare(strict_types=1);

return [
    'body' => 'Contenido',
    'placeholder' => '¿Qué lanzaste?',
    'poll_placeholder' => 'Haz una pregunta',
    'commit' => 'Commit',
    'committing' => 'Haciendo commit…',
    'commit_title' => 'Commit (:keys)',

    'dialog' => [
        'title' => 'Escribir un commit',
        'description' => 'Tu commit se añade a tu log en cuanto lo envías.',
    ],

    'guest' => [
        'title' => 'Una red social para desarrolladores.',
        'description' => 'Todos aquí abajo iniciaron sesión con GitHub e hicieron commit de texto plano. Te toca.',
        'sign_in_to_commit' => 'Inicia sesión con GitHub para hacer commit',
        'profile' => 'Tu perfil viene de GitHub. No hay nada que llenar.',
        'sign_in' => 'Iniciar sesión con GitHub',
    ],

    'schedule' => [
        'button' => 'Programar',
        'submit_at' => 'Enviar el',
        'commit' => 'Programar commit',
        'scheduling' => 'Programando…',
        'commit_title' => 'Programar commit (:keys)',
        'nonexistent_time' => 'Esta hora no existe en tu zona horaria. Elige otra hora.',
        'help_admin' => ':timezone. Dentro de 30 días. Privado hasta que se envíe; tu commit se aprobará automáticamente.',
        'help_review' => ':timezone. Dentro de 30 días. Privado hasta que se envíe; luego pasará a revisión, no se publicará automáticamente.',
        'scheduled_commits' => 'Commits programados →',
        'images_unsupported' => 'Aún no se pueden programar imágenes. Quítalas para programar este commit.',
        'images_unsupported_title' => 'Aún no se pueden programar imágenes',
        'polls_unsupported' => 'Aún no se pueden programar encuestas',
    ],

    'poll' => [
        'add' => 'Añadir encuesta',
        'images_or_poll' => 'Un commit puede tener imágenes o una encuesta, no ambas',
        'legend' => 'Encuesta',
        'option' => 'Opción :number',
        'remove_option' => 'Quitar opción :number',
        'remove_option_title' => 'Quitar opción',
        'add_option' => 'Añadir opción',
        'length' => 'Duración de la encuesta',
        'remove' => 'Quitar encuesta',
        'hours' => ':count hora|:count horas',
        'days' => ':count día|:count días',
    ],

    'images' => [
        'invalid' => 'Solo imágenes JPEG, PNG, GIF o WebP de hasta 10 MB.',
        'too_many' => 'Hasta :max imágenes por commit.',
        'attach' => 'Adjuntar imágenes',
        'attach_title' => 'Adjunta hasta :max imágenes',
        'attached' => 'Imágenes adjuntas',
        'attached_alt' => 'Imagen adjunta :number',
        'remove' => 'Quitar imagen :number',
        'edit_description' => 'Editar descripción de la imagen :number',
        'add_description' => 'Añadir descripción a la imagen :number',
        'description_label' => 'Descripción de la imagen :number',
        'description_placeholder' => 'Describe la imagen para quienes no pueden verla',
        'done' => 'Listo',
    ],

    'preview' => [
        'label' => 'Vista previa',
        'title' => 'Vista previa (:keys)',
        'empty' => 'Aún no hay nada que previsualizar.',
        'link_card' => 'Se convierte en una tarjeta de enlace después de hacer commit.',
    ],

    'link_card' => [
        'title' => 'Elige qué enlace tendrá tarjeta',
        'label' => 'Tarjeta de enlace',
        'sr_label' => 'Tarjeta de enlace:',
        'none' => 'Sin tarjeta',
    ],

    'counter' => [
        'remaining' => 'Queda :count carácter|Quedan :count caracteres',
        'fold_hint' => 'Los feeds muestran los primeros :length caracteres o :lines líneas, y luego “Mostrar más”.',
    ],

    'stash' => [
        'restored' => 'Se restauró tu borrador sin enviar',
        'drop_title' => 'Descartar el borrador restaurado',
    ],

    'mentions' => [
        'emoji_label' => 'Insertar un emoji',
        'label' => 'Mencionar a un desarrollador',
        'following' => 'Personas que sigues',
        'searching' => 'Buscando…',
        'no_match' => "fatal: ningún desarrollador coincide con ':query'",
    ],

    'secrets' => [
        'warning' => 'Aviso de texto sensible',
        'detected' => 'Posible secreto detectado|Posibles secretos detectados',
        'checked_locally' => 'Revisado en tu dispositivo. Este borrador no se guardará mientras contenga un secreto detectado.',
        'select' => 'Seleccionar posible secreto',
        'select_numbered' => 'Seleccionar posible secreto :number',
        'remove_detected' => 'Quitar secretos detectados',
        'confirm_title' => '¿Publicar posibles secretos?',
        'confirm_description' => 'Este texto puede contener credenciales. Quítalas antes de publicar, o continúa solo si son ejemplos seguros.',
        'keep_editing' => 'Seguir editando',
        'remove' => 'Quitar secretos',
        'publish_anyway' => 'Publicar de todos modos',
    ],

    'quote' => [
        'button' => 'Citar',
        'button_title' => 'Citar (:keys)',
        'unsent' => 'Tienes una cita sin enviar aquí',
        'title' => 'Citar a :handle',
        'description' => 'Escribe tu propio commit. Este commit aparecerá incrustado debajo.',
        'label' => 'Tu commit',
        'placeholder' => 'Añade tu opinión',
        'cancel' => 'Cancelar',
        'submit' => 'Citar',
        'quoting' => 'Citando…',
    ],
];
