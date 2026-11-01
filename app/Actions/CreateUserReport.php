<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserReport;

final readonly class CreateUserReport
{
    /**
     * Reporting someone again while the first report is open keeps the first report.
     */
    public function handle(User $reporter, User $user, string $reason, ?string $details): void
    {
        UserReport::query()->createOrFirst([
            'user_id' => $user->id,
            'reporter_id' => $reporter->id,
        ], [
            'reason' => $reason,
            'details' => $details,
        ]);
    }
}
