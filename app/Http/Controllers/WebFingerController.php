<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowWebFingerRequest;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Http\JsonResponse;

final readonly class WebFingerController
{
    public function show(ShowWebFingerRequest $request, ActivityStreams $streams): JsonResponse
    {
        $user = User::query()
            ->whereRaw('lower(username) = ?', [mb_strtolower($request->username())])
            ->where('federates', true)
            ->firstOrFail();

        return response()->json($streams->webFinger($user), headers: [
            'Content-Type' => 'application/jrd+json',
            'Access-Control-Allow-Origin' => '*',
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
