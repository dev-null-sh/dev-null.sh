<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('EXPLORE_ENABLED', true),
    'cohort_size' => 500,
    'wait_minutes' => 15,
    'request_size' => 50,
    'prompt_bytes' => 32768,
    'catalog_size' => 32,
    'summary_size' => 20,
    'model' => 'deepseek-v4.1-flash',
];
