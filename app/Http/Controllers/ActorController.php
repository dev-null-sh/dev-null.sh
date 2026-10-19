<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserKeyPair;
use App\Http\Requests\ShowActorRequest;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Http\JsonResponse;

final readonly class ActorController
{
    public function show(ShowActorRequest $request, User $user, ActivityStreams $streams, CreateUserKeyPair $createUserKeyPair): JsonResponse
    {
        return response()->json($streams->actor($createUserKeyPair->handle($user)), headers: [
            'Content-Type' => ActivityStreams::CONTENT_TYPE,
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
