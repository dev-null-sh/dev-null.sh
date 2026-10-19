<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRemoteFollower;
use App\Http\Requests\CreateActorActivityRequest;
use App\Models\User;
use App\Services\ActivityStreams;
use Illuminate\Http\Response;

final readonly class ActorInboxController
{
    public function store(CreateActorActivityRequest $request, User $user, ActivityStreams $streams, CreateRemoteFollower $createRemoteFollower): Response
    {
        $activity = $request->activity();
        $follow = ($activity['type'] ?? null) === 'Follow' ? $activity : $activity['object'] ?? null;

        if (! $request->isActedOn() || ! is_array($follow) || ($follow['type'] ?? null) !== 'Follow' || ($follow['object'] ?? null) !== $streams->actorUrl($user)) {
            return response()->noContent(202);
        }

        if ($activity['type'] === 'Follow') {
            $createRemoteFollower->handle($user, $activity, $request->signer());
        } else {
            $user->remoteFollowers()->where('actor_url', $request->signer()['id'])->delete();
        }

        return response()->noContent(202);
    }
}
