<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Omarchy Theme
    |--------------------------------------------------------------------------
    |
    | When enabled, the app adopts the colors of the currently active Omarchy
    | desktop theme. Everything below degrades silently: if the colors file
    | cannot be found, the "Omarchy" appearance option is simply unavailable.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Omarchy Theme Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the colors file of the applied Omarchy theme. A glob is
    | allowed and the first match wins, so the app works for any host user
    | without configuration. A host without Omarchy simply never matches.
    |
    */

    'path' => '/home/*/.local/state/omarchy/current/theme/colors.toml',

];
