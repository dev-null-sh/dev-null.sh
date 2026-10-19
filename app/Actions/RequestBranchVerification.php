<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\CheckPendingBranchDnsVerifications;
use App\Models\Branch;
use Illuminate\Validation\ValidationException;

/**
 * Queues a branch for DNS checking, then moderator review once the TXT record matches.
 */
final readonly class RequestBranchVerification
{
    public function handle(Branch $branch): Branch
    {
        if ($branch->isVerified()) {
            return $branch;
        }

        if (! $branch->isOwned()) {
            throw ValidationException::withMessages(['branch' => 'Own this branch before requesting verification.']);
        }

        if ($branch->domain === null || $branch->verification_token === null) {
            throw ValidationException::withMessages(['domain' => 'Set a domain for this branch first.']);
        }

        if ($branch->verification_requested_at === null) {
            $branch->forceFill([
                'verification_requested_at' => now(),
                'txt_matched_at' => null,
            ])->save();
        }

        CheckPendingBranchDnsVerifications::dispatch($branch->refresh());

        return $branch->refresh();
    }
}
