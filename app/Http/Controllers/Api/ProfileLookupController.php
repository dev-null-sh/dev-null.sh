<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateProfileLookup;
use App\Http\Requests\CreateApiProfileLookupRequest;
use App\Http\Requests\ShowApiProfileLookupRequest;
use App\Queries\ProfileLookupQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A handle nobody on /dev/null has may still be someone's on GitHub. The app looks it up, then polls where the lookup
 * stands until GitHub answers, as the web's lookup page does.
 */
final readonly class ProfileLookupController
{
    public function show(ShowApiProfileLookupRequest $request, ProfileLookupQuery $lookups): JsonResponse
    {
        $lookup = $lookups->get($request->handle());

        return response()->json(['status' => $lookup['status'], 'resumes_at' => $lookup['resumesAt']]);
    }

    public function store(CreateApiProfileLookupRequest $request, CreateProfileLookup $action): Response
    {
        $action->handle($request->handle());

        return response()->noContent(202);
    }
}
