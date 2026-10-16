<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledCommit;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class CreateScheduledCommit
{
    public function handle(User $user, string $body, CarbonImmutable $scheduledAt): ScheduledCommit
    {
        return ScheduledCommit::query()->create([
            'user_id' => $user->id,
            'body' => mb_trim($body),
            'scheduled_at' => $scheduledAt->utc(),
        ]);
    }
}
