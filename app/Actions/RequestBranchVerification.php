<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchDnsVerification;
use Illuminate\Validation\ValidationException;

/**
 * Queues a branch for moderator review once its TXT record is present.
 */
final readonly class RequestBranchVerification
{
    public function __construct(private BranchDnsVerification $verification)
    {
        //
    }

    public function handle(Branch $branch): Branch
    {
        if ($branch->isVerified()) {
            return $branch;
        }

        if ($branch->domain === null || $branch->verification_token === null) {
            throw ValidationException::withMessages(['domain' => 'Claim a domain for this branch first.']);
        }

        if (! $this->verification->matches($branch)) {
            throw ValidationException::withMessages([
                'domain' => 'No matching TXT record at '.$this->verification->hostFor($branch->domain).'.',
            ]);
        }

        if ($branch->verification_requested_at === null) {
            $branch->forceFill(['verification_requested_at' => now()])->save();
        }

        return $branch->refresh();
    }
}
