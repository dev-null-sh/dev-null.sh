<?php

declare(strict_types=1);

return [
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'opencode' => [
            'driver' => 'openai-compatible',
            'key' => env('OPENCODE_API_KEY'),
            'url' => 'https://opencode.ai/zen/v1',
        ],
    ],
];
