<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiRemoteCandidatesRequest;
use App\Http\Resources\RemoteCandidateResource;
use App\Models\User;
use App\Queries\RemoteCandidatesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class RemoteCandidateController
{
    /**
     * The people matching a search, each with the reason the user can't message them or add them to the org, as the
     * web's New remote and New org pickers show them.
     */
    public function index(ListApiRemoteCandidatesRequest $request, #[CurrentUser] User $user, RemoteCandidatesQuery $candidates): JsonResponse
    {
        return response()->json([
            'candidates' => RemoteCandidateResource::collection($candidates->get($user, $request->string('q')->value(), $request->org())),
        ])->header('Cache-Control', 'private, no-store');
    }
}
