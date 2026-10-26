<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The addresses of the load balancers or proxies in front of the app, as a
    | comma separated list of IPs or CIDR ranges in your ".env" file, or "*"
    | when the app is only reachable through them.
    |
    | Abuse limits key on the client's address, so this decides what "the
    | client's address" means. Left empty, no proxy is trusted and the
    | connection's own address is used, which is right for a server that
    | terminates TLS itself. Behind a proxy that is left unset, every client
    | shares one address, so one of them can exhaust a limit for all of them.
    |
    | Trusting an address that is reachable from the internet lets whoever is
    | there forge its own, so list only proxies you control.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
