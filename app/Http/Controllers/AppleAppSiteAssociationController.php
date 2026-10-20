<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final readonly class AppleAppSiteAssociationController
{
    /**
     * Which links iOS opens in the mobile app: profiles, their followers, commits and remotes. A profile's files stay in
     * the browser.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'details' => [[
                    'appIDs' => config('mobile.ios_app_ids'),
                    'components' => [
                        ...collect(['og.png', 'feed.atom', 'graph.svg', 'preview'])
                            ->map(fn (string $file): array => ['/' => "/@*/{$file}", 'exclude' => true]),
                        ['/' => '/@*'],
                        ['/' => '/remotes/@*'],
                    ],
                ]],
            ],
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
