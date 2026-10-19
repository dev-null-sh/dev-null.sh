<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchDnsVerification;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a pending verification after a moderator re-checks the TXT record.
 */
final readonly class ApproveBranchVerification
{
    public function __construct(
        private BranchDnsVerification $verification,
        private RefreshBranchSiteMeta $refreshSiteMeta,
    ) {
        //
    }

    public function handle(Branch $branch): Branch
    {
        if ($branch->verification_requested_at === null) {
            throw ValidationException::withMessages(['branch' => 'This branch is not waiting for verification.']);
        }

        if ($branch->domain === null || $branch->verification_token === null) {
            throw ValidationException::withMessages(['domain' => 'This branch has no domain to verify.']);
        }

        if (! $this->verification->matches($branch)) {
            throw ValidationException::withMessages([
                'domain' => 'No matching TXT record at '.$this->verification->hostFor($branch->domain).'.',
            ]);
        }

        $branch->forceFill([
            'verified_at' => now(),
            'verified_domain' => $branch->domain,
            'verification_requested_at' => null,
        ])->save();

        return $this->refreshSiteMeta->handle($branch->refresh());
    }
}
