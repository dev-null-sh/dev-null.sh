<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RejectionReason;

final readonly class CreateRejectionReason
{
    public function handle(string $title, string $body): RejectionReason
    {
        return RejectionReason::query()->create([
            'title' => $title,
            'body' => $body,
        ]);
    }
}
