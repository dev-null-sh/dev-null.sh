<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SSH Server
    |--------------------------------------------------------------------------
    |
    | The `ssh:serve` command serves the devnull terminal app over SSH. It
    | listens on a non-standard high port, so it needs no privileges and
    | never collides with the sshd of the machine it runs on.
    |
    */

    'host' => env('SSH_HOST', '0.0.0.0'),

    'port' => (int) env('SSH_PORT', 42022),

    'idle_timeout' => (int) env('SSH_IDLE_TIMEOUT', 900),

    /*
    |--------------------------------------------------------------------------
    | Key Owner Lookups
    |--------------------------------------------------------------------------
    |
    | Users log in with the SSH keys mirrored from GitHub. `ssh:serve` never
    | queries the database: it asks the web app at APP_URL who owns a key,
    | sending this token, which the web app must be configured with too.
    |
    */

    'lookup_token' => env('SSH_LOOKUP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Host Key
    |--------------------------------------------------------------------------
    |
    | Host keys are generated in the key path on first boot. Where storage is
    | ephemeral, set SSH_HOST_KEY to a base64 encoded Ed25519 secret key so
    | the host key survives deploys and clients don't see it change.
    |
    */

    'key_path' => env('SSH_KEY_PATH', storage_path('app/private/ssh')),

    'host_key' => env('SSH_HOST_KEY'),

];
