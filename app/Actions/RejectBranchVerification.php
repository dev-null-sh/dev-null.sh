<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use Illuminate\Validation\ValidationException;

/**
 * Drops a pending verification request; ownership and domain stay.
 */
final readonly class RejectBranchVerification
{
    public function handle(Branch $branch): Branch
    {
        if ($branch->verification_requested_at === null) {
            throw ValidationException::withMessages(['branch' => 'This branch is not waiting for verification.']);
        }

        $branch->forceFill(['verification_requested_at' => null])->save();

        return $branch->refresh();
    }
}
