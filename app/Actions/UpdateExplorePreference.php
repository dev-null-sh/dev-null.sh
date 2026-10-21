<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExplorePreference;
use App\Models\User;

final readonly class UpdateExplorePreference
{
    public function handle(User $user, bool $personalized): void
    {
        ExplorePreference::query()->updateOrCreate(['user_id' => $user->id], ['preferences' => ['personalized' => $personalized]]);
    }
}
