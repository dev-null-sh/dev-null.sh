<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchStyles;
use App\Services\FaviconBrandColor;

/**
 * Pulls homepage meta and favicon brand colour for a branch's domain (used on verify and refresh).
 */
final readonly class RefreshBranchSiteMeta
{
    public function __construct(
        private FetchDomainSiteMeta $siteMeta,
        private FaviconBrandColor $faviconColor,
        private BranchStyles $branchStyles,
    ) {
        //
    }

    public function handle(Branch $branch): Branch
    {
        $domain = $branch->verified_domain ?? $branch->domain;

        if ($domain === null) {
            return $branch;
        }

        $siteMeta = $this->siteMeta->forDomain($domain);

        $branch->forceFill([
            'domain_description' => $siteMeta['description'],
            'og_image_url' => $siteMeta['image_url'],
            'brand_color' => $this->faviconColor->forDomain($domain),
            'description' => $branch->description ?? $siteMeta['description'],
        ])->save();

        $this->branchStyles->forget();

        return $branch->refresh();
    }

    /**
     * Refreshes every verified branch. Returns how many branches were updated.
     */
    public function handleVerified(): int
    {
        $refreshed = 0;

        Branch::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_domain')
            ->orderBy('id')
            ->each(function (Branch $branch) use (&$refreshed): void {
                $this->handle($branch);
                $refreshed++;
            });

        return $refreshed;
    }
}
