<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PushSubscription;
use App\Models\User;

final readonly class CreatePushSubscription
{
    public function handle(User $user, string $endpoint): PushSubscription
    {
        // One browser subscription belongs to only the account currently using it.
        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            ['user_id' => $user->id, 'endpoint' => $endpoint],
        );
    }
}
