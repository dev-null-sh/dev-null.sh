<?php

declare(strict_types=1);

use NotificationChannels\WebPush\PushSubscription;

return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    'database_connection' => null,

    'client_options' => [],

    'automatic_padding' => true,

];
