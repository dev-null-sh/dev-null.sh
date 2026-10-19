<?php

declare(strict_types=1);

return [
    // Explicitly opt in on a loopback development server. This disables all mirror HTTP traffic.
    'demo' => env('DEVNULL_REACTION_DEMO', false),
];
