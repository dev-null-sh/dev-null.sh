<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RefreshTagSiteMeta;
use App\Models\Tag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('branches:refresh-site-meta {branch? : Optional branch name to refresh}')]
#[Description('Refresh verified branch descriptions, og:images, and favicon brand colours')]
final class RefreshTagSiteMetaCommand extends Command
{
    public function handle(RefreshTagSiteMeta $action): int
    {
        $name = $this->argument('branch');

        if (is_string($name) && $name !== '') {
            $tag = Tag::query()->where('name', mb_strtolower($name))->first();

            if (! $tag instanceof Tag) {
                $this->components->error("No branch named {$name}.");

                return self::FAILURE;
            }

            if (! $tag->isVerified()) {
                $this->components->error("#{$tag->name} is not verified.");

                return self::FAILURE;
            }

            $tag = $action->handle($tag);
            $this->components->info(
                "#{$tag->name} ← {$tag->verified_domain}".
                ($tag->brand_color ? " ({$tag->brand_color})" : ' (no brand colour)'),
            );

            return self::SUCCESS;
        }

        $refreshed = 0;

        Tag::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_domain')
            ->orderBy('name')
            ->each(function (Tag $tag) use ($action, &$refreshed): void {
                $tag = $action->handle($tag);
                $refreshed++;
                $this->components->twoColumnDetail(
                    "#{$tag->name}",
                    ($tag->brand_color ?? '—').' · '.($tag->verified_domain ?? ''),
                );
            });

        $this->components->info("Refreshed site meta for {$refreshed} verified branches.");

        return self::SUCCESS;
    }
}
