<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\User;

final readonly class UpdateUserDateTime
{
    public function handle(User $user, ?DateFormat $dateFormat, ?TimeFormat $timeFormat): void
    {
        $user->update([
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
        ]);
    }
}
