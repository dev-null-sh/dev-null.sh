<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RejectionReason;

final readonly class DeleteRejectionReason
{
    public function handle(RejectionReason $rejectionReason): void
    {
        $rejectionReason->delete();
    }
}
