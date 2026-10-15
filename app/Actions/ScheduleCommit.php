<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledCommit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ScheduleCommit
{
    public function handle(User $user, string $body, CarbonImmutable $scheduledAt): ScheduledCommit
    {
        return DB::transaction(function () use ($user, $body, $scheduledAt): ScheduledCommit {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($scheduledAt->lessThanOrEqualTo(now()) || $scheduledAt->greaterThan(now()->addDays(30))) {
                throw ValidationException::withMessages(['scheduled_at' => __('Choose a future time within the next 30 days.')]);
            }

            if (ScheduledCommit::query()->where('user_id', $user->id)->whereNull('submitted_at')->whereNull('canceled_at')->count() >= 10) {
                throw ValidationException::withMessages(['scheduled_at' => __('You can have up to 10 scheduled commits. Cancel one before scheduling another.')]);
            }

            return ScheduledCommit::query()->create([
                'user_id' => $user->id,
                'body' => mb_trim($body),
                'scheduled_at' => $scheduledAt->utc(),
            ]);
        }, attempts: 5);
    }
}
