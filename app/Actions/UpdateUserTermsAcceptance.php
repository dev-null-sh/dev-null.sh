<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserTermsAcceptance
{
    /**
     * Records that they accepted the terms and privacy policy as they stand now.
     */
    public function handle(User $user): void
    {
        $user->update(['terms_accepted_at' => now()]);
    }
}
