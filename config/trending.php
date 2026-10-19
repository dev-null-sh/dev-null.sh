<?php

declare(strict_types=1);

return [

    // How much the Trending log reshuffles on each visit (see TrendingLogQuery). Configurable only so tests can set it
    // to 0 and get a deterministic order.
    'temperature' => (float) env('TRENDING_TEMPERATURE', 0.5),

];
