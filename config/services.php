<?php

declare(strict_types=1);

return [

    'web_push' => [
        'subject' => env('WEB_PUSH_SUBJECT', 'https://dev-null.sh'),
        'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
        'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
    ],

    // A Firebase service account key, split so each part that differs per environment is a variable of its own. The
    // rest is the same in every key Google hands out. Platforms that store environment variables on one line, such as
    // Laravel Cloud, carry the private key's newlines as "\n", so they are turned back into newlines here.
    'fcm' => [
        'credentials' => [
            'type' => 'service_account',
            'project_id' => env('FCM_PROJECT_ID'),
            'private_key_id' => env('FCM_PRIVATE_KEY_ID'),
            'private_key' => str_replace('\n', "\n", (string) env('FCM_PRIVATE_KEY')),
            'client_email' => env('FCM_CLIENT_EMAIL'),
            'client_id' => env('FCM_CLIENT_ID'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/'.rawurlencode((string) env('FCM_CLIENT_EMAIL')),
            'universe_domain' => 'googleapis.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'cloudflare_turn' => [
        'key_id' => env('CLOUDFLARE_TURN_KEY_ID'),
        'api_token' => env('CLOUDFLARE_TURN_API_TOKEN'),
    ],

    'cloudflare_turnstile' => [
        'site_key' => env('CLOUDFLARE_TURNSTILE_SITE_KEY'),
        'secret_key' => env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
    ],

    'fathom' => [
        'token' => env('FATHOM_API_TOKEN'),
        'site_id' => env('FATHOM_SITE_ID', 'HTQONOPD'),
    ],

    'github' => [
        'repository' => env('GITHUB_REPOSITORY', 'dev-null-sh/dev-null.sh'),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
        'sponsors_token' => env('GITHUB_SPONSORS_TOKEN'),
    ],

    'github_app' => [
        'id' => env('GITHUB_APP_ID'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
