<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DeliverActivity;
use App\Models\RemoteFollower;
use App\Models\User;
use App\Services\ActivityStreams;
use App\Services\RemoteActors;
use Uri\WhatWg\Url;

final readonly class CreateRemoteFollower
{
    public function __construct(private ActivityStreams $streams, private RemoteActors $actors)
    {
        //
    }

    /**
     * @param  array<mixed>  $follow
     * @param  array<mixed>  $actor
     */
    public function handle(User $user, array $follow, array $actor): void
    {
        $inbox = $actor['inbox'] ?? null;
        $endpoints = $actor['endpoints'] ?? null;
        $sharedInbox = is_array($endpoints) ? ($endpoints['sharedInbox'] ?? null) : null;

        if (! is_string($actor['id'] ?? null) || ! is_string($inbox) || ! $this->actors->allowedUrl($inbox) instanceof Url) {
            return;
        }

        RemoteFollower::query()->updateOrCreate([
            'user_id' => $user->id,
            'actor_url' => $actor['id'],
        ], [
            'inbox_url' => $inbox,
            'shared_inbox_url' => is_string($sharedInbox) && $this->actors->allowedUrl($sharedInbox) instanceof Url ? $sharedInbox : null,
        ]);

        dispatch(new DeliverActivity($user, $inbox, $this->streams->accept($user, $follow)));
    }
}
