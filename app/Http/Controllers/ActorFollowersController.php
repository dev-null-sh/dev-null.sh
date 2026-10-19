<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListActorFollowersRequest;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Http\JsonResponse;

final readonly class ActorFollowersController
{
    public function index(ListActorFollowersRequest $request, User $user, ActivityStreams $streams): JsonResponse
    {
        return response()->json($streams->collection(route('actor-followers.index', $user), $user->remoteFollowers()->count()), headers: [
            'Content-Type' => ActivityStreams::CONTENT_TYPE,
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
