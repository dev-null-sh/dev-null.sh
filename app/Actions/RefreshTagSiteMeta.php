<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Services\BranchStyles;
use App\Services\FaviconBrandColor;

/**
 * Pulls homepage meta and favicon brand colour for a tag's domain (used on verify and refresh).
 */
final readonly class RefreshTagSiteMeta
{
    public function __construct(
        private FetchDomainSiteMeta $siteMeta,
        private FaviconBrandColor $faviconColor,
        private BranchStyles $branchStyles,
    ) {
        //
    }

    public function handle(Tag $tag): Tag
    {
        $domain = $tag->verified_domain ?? $tag->domain;

        if ($domain === null) {
            return $tag;
        }

        $siteMeta = $this->siteMeta->forDomain($domain);

        $tag->forceFill([
            'domain_description' => $siteMeta['description'],
            'og_image_url' => $siteMeta['image_url'],
            'brand_color' => $this->faviconColor->forDomain($domain),
            'description' => $tag->description ?? $siteMeta['description'],
        ])->save();

        $this->branchStyles->forget();

        return $tag->refresh();
    }

    /**
     * Refreshes every verified branch. Returns how many tags were updated.
     */
    public function handleVerified(): int
    {
        $refreshed = 0;

        Tag::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_domain')
            ->orderBy('id')
            ->each(function (Tag $tag) use (&$refreshed): void {
                $this->handle($tag);
                $refreshed++;
            });

        return $refreshed;
    }
}
