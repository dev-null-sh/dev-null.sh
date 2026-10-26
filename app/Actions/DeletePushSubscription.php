<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PushSubscription;
use App\Models\User;

final readonly class DeletePushSubscription
{
    public function handle(User $user, string $endpointHash): void
    {
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', $endpointHash)->delete();
    }
}
