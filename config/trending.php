<?php

declare(strict_types=1);

use App\Services\TheAlgorithm;

return [

    // How much the Trending log reshuffles on each visit (see TheAlgorithm::TEMPERATURE). Configurable only so tests
    // can set it to 0 and get a deterministic order.
    'temperature' => (float) env('TRENDING_TEMPERATURE', TheAlgorithm::TEMPERATURE),

];
