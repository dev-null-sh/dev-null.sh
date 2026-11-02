<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserEmailsUnsubscribedAt
{
    public function handle(User $user): void
    {
        $user->update(['emails_unsubscribed_at' => $user->emails_unsubscribed_at ?? now()]);
    }
}
