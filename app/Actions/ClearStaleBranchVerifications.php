<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchStyles;
use App\Services\BranchDnsVerification;

final readonly class ClearStaleBranchVerifications
{
    public function __construct(
        private BranchDnsVerification $verification,
        private BranchStyles $branchStyles,
    ) {
        //
    }

    /**
     * Drops the verified badge when the TXT record is gone; membership stays.
     */
    public function handle(): int
    {
        $cleared = 0;

        Branch::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('domain')
            ->whereNotNull('verification_token')
            ->orderBy('id')
            ->each(function (Branch $branch) use (&$cleared): void {
                if ($this->verification->matches($branch)) {
                    return;
                }

                $branch->forceFill([
                    'verified_at' => null,
                    'verified_domain' => null,
                    'verification_requested_at' => null,
                    'domain_description' => null,
                    'og_image_url' => null,
                    'brand_color' => null,
                ])->save();

                $cleared++;
            });

        if ($cleared > 0) {
            $this->branchStyles->forget();
        }

        return $cleared;
    }
}
