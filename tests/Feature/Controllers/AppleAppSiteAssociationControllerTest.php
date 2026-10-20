<?php

declare(strict_types=1);

it('tells iOS which links open in the mobile app', function (): void {
    config(['mobile.ios_app_ids' => ['TEAM.com.example.devnull']]);

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'applinks' => [
                'details' => [[
                    'appIDs' => ['TEAM.com.example.devnull'],
                    'components' => [
                        ['/' => '/@*/og.png', 'exclude' => true],
                        ['/' => '/@*/feed.atom', 'exclude' => true],
                        ['/' => '/@*/graph.svg', 'exclude' => true],
                        ['/' => '/@*/preview', 'exclude' => true],
                        ['/' => '/@*'],
                        ['/' => '/remotes/@*'],
                    ],
                ]],
            ],
        ]);
});
