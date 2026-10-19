<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Private Addresses
    |--------------------------------------------------------------------------
    |
    | Whether federation may reach servers on private or local addresses, and on
    | any port. Only turn this on to test with a Mastodon running locally: in
    | production it would let a remote server make /dev/null call internal hosts.
    |
    */

    'allow_private_addresses' => (bool) env('FEDERATION_ALLOW_PRIVATE_ADDRESSES', false),

];
