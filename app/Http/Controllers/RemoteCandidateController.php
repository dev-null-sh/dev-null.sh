<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListRemoteCandidatesRequest;
use App\Models\User;
use App\Queries\RemoteCandidatesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class RemoteCandidateController
{
    public function index(ListRemoteCandidatesRequest $request, #[CurrentUser] User $user, RemoteCandidatesQuery $candidates): JsonResponse
    {
        return response()->json([
            'candidates' => $candidates->get($user, $request->string('q')->value(), $request->org(), forSharing: $request->isSharing()),
        ])->header('Cache-Control', 'private, no-store');
    }
}
