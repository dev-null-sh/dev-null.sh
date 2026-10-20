<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final readonly class AssetLinksController
{
    /**
     * Lets Android open dev-null.sh links in the mobile app. The app's manifest says which ones.
     */
    public function show(): JsonResponse
    {
        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('mobile.android_package'),
                'sha256_cert_fingerprints' => config('mobile.android_certificate_fingerprints'),
            ],
        ]], options: JSON_UNESCAPED_SLASHES);
    }
}
