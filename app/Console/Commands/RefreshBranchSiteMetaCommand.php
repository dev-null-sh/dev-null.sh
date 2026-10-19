<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RefreshBranchSiteMeta;
use App\Models\Branch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('branches:refresh-site-meta {branch? : Optional branch name to refresh}')]
#[Description('Refresh verified branch descriptions, og:images, and favicon brand colours')]
final class RefreshBranchSiteMetaCommand extends Command
{
    public function handle(RefreshBranchSiteMeta $action): int
    {
        $name = $this->argument('branch');

        if (is_string($name) && $name !== '') {
            $branch = Branch::query()->where('name', mb_strtolower($name))->first();

            if (! $branch instanceof Branch) {
                $this->components->error("No branch named {$name}.");

                return self::FAILURE;
            }

            if (! $branch->isVerified()) {
                $this->components->error("#{$branch->name} is not verified.");

                return self::FAILURE;
            }

            $branch = $action->handle($branch);
            $this->components->info(
                "#{$branch->name} ← {$branch->verified_domain}".
                ($branch->brand_color ? " ({$branch->brand_color})" : ' (no brand colour)'),
            );

            return self::SUCCESS;
        }

        $refreshed = 0;

        Branch::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_domain')
            ->orderBy('name')
            ->each(function (Branch $branch) use ($action, &$refreshed): void {
                $branch = $action->handle($branch);
                $refreshed++;
                $this->components->twoColumnDetail(
                    "#{$branch->name}",
                    ($branch->brand_color ?? '—').' · '.($branch->verified_domain ?? ''),
                );
            });

        $this->components->info("Refreshed site meta for {$refreshed} verified branches.");

        return self::SUCCESS;
    }
}
