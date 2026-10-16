<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RejectionReason;

final readonly class UpdateRejectionReason
{
    public function handle(RejectionReason $rejectionReason, string $title, string $body): void
    {
        $rejectionReason->update([
            'title' => $title,
            'body' => $body,
        ]);
    }
}
