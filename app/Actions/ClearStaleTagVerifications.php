<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Services\BranchStyles;
use App\Services\TagDnsVerification;

final readonly class ClearStaleTagVerifications
{
    public function __construct(
        private TagDnsVerification $verification,
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

        Tag::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('domain')
            ->whereNotNull('verification_token')
            ->orderBy('id')
            ->each(function (Tag $tag) use (&$cleared): void {
                if ($this->verification->matches($tag)) {
                    return;
                }

                $tag->forceFill([
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
