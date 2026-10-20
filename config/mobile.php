<?php

declare(strict_types=1);

return [

    /*
     * Where the authorize page may send the devnull app back to with its code. A redirect_uri has to start with one of
     * these, so a code never leaves for anywhere but the app's own URL scheme.
     */
    'redirect_uri_prefixes' => ['devnull://'],

    /*
     * The app that opens dev-null.sh links when it's installed, as the files phones fetch from /.well-known name it.
     * iOS wants its team and bundle ID. Android wants its package name and the SHA-256 fingerprints of the keys it's
     * signed with.
     */
    'ios_app_ids' => explode(',', (string) env('MOBILE_IOS_APP_IDS', 'J68WFCX458.com.nativephp.devnull')),

    'android_package' => env('MOBILE_ANDROID_PACKAGE', 'com.nativephp.devnull'),

    'android_certificate_fingerprints' => array_filter(explode(',', (string) env('MOBILE_ANDROID_CERTIFICATE_FINGERPRINTS'))),

];
