<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    |
    | Whether someone can push to a person who doesn't follow them. When on,
    | their first message waits in the recipient's Fetched tab until it is
    | merged or dropped. When off, only people who follow you can be pushed to.
    |
    */

    'requests' => (bool) env('REMOTES_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Everyone
    |--------------------------------------------------------------------------
    |
    | Whether people can let everyone push to them straight away, instead of
    | only the people they follow.
    |
    */

    'everyone' => (bool) env('REMOTES_EVERYONE', false),

];
