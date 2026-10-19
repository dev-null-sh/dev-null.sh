<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;

final readonly class UpdateBranchDescription
{
    public function handle(Branch $branch, ?string $description): Branch
    {
        $branch->forceFill([
            'description' => filled($description) ? mb_trim($description) : null,
        ])->save();

        return $branch->refresh();
    }
}
