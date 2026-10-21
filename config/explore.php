<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('EXPLORE_ENABLED', true),
    'cohort_size' => (int) env('EXPLORE_COHORT_SIZE', 500),
    'wait_minutes' => (int) env('EXPLORE_WAIT_MINUTES', 15),
    'request_size' => (int) env('EXPLORE_REQUEST_SIZE', 50),
    'prompt_bytes' => (int) env('EXPLORE_PROMPT_BYTES', 32768),
    'catalog_size' => (int) env('EXPLORE_CATALOG_SIZE', 32),
    'summary_size' => (int) env('EXPLORE_SUMMARY_SIZE', 20),
    'model' => env('EXPLORE_MODEL', 'deepseek-v4.1-flash'),
];
