<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListActorOutboxRequest;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Http\JsonResponse;

final readonly class ActorOutboxController
{
    public function index(ListActorOutboxRequest $request, User $user, ActivityStreams $streams): JsonResponse
    {
        $commits = $user->commits()->approved()->whereNull('parent_id')->count();

        return response()->json($streams->collection(route('actor-outbox.index', $user), $commits), headers: [
            'Content-Type' => ActivityStreams::CONTENT_TYPE,
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
