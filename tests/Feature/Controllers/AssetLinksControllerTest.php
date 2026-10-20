<?php

declare(strict_types=1);

it('lets Android open links in the mobile app signed with a listed key', function (): void {
    config([
        'mobile.android_package' => 'com.example.devnull',
        'mobile.android_certificate_fingerprints' => ['AB:CD'],
    ]);

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.example.devnull',
                'sha256_cert_fingerprints' => ['AB:CD'],
            ],
        ]]);
});
