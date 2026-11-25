<?php

declare(strict_types=1);

return [
    'api_path' => 'api/v1',

    'export_path' => 'resources/api/openapi.json',

    'ui' => [
        'title' => '/dev/null',
    ],

    'info' => [
        'version' => '1',
        'description' => 'The JSON API the /dev/null apps use. Reading public profiles and commits needs nothing; acting for someone needs a request signed with an SSH key they approved as a device.',
    ],

    'servers' => [
        'Production' => 'https://dev-null.sh/api/v1',
    ],
];
