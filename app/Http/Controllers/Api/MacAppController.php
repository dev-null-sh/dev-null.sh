<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Queries\MacAppReleaseQuery;
use Illuminate\Http\JsonResponse;

final readonly class MacAppController
{
    /**
     * The release the /apps page offers, which the Mac app compares its own build with to check for updates. Public and
     * unsigned, so checking sends nothing about the user; null until a release is published.
     */
    public function show(MacAppReleaseQuery $releases): JsonResponse
    {
        return response()->json(['data' => $releases->get()]);
    }
}
