<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchDnsVerification;

/**
 * Marks pending verifications ready for moderators once their TXT record matches.
 */
final readonly class CheckPendingBranchDnsVerifications
{
    public function __construct(private BranchDnsVerification $verification)
    {
        //
    }

    public function handle(?Branch $only = null): int
    {
        $matched = 0;

        $query = Branch::query()
            ->whereNotNull('verification_requested_at')
            ->whereNull('verified_at')
            ->whereNull('txt_matched_at')
            ->whereNotNull('domain')
            ->whereNotNull('verification_token')
            ->orderBy('id');

        if ($only instanceof Branch) {
            $query->whereKey($only->id);
        }

        $query->each(function (Branch $branch) use (&$matched): void {
            if (! $this->verification->matches($branch)) {
                return;
            }

            $branch->forceFill(['txt_matched_at' => now()])->save();
            $matched++;
        });

        return $matched;
    }
}
