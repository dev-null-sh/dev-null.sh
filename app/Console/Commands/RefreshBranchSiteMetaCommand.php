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
            return $this->refreshOne($action, mb_strtolower($name));
        }

        return $this->refreshVerified($action);
    }

    private function refreshOne(RefreshBranchSiteMeta $action, string $name): int
    {
        $branch = Branch::query()->where('name', $name)->first();

        if (! $branch instanceof Branch) {
            $this->components->error("No branch named {$name}.");

            return self::FAILURE;
        }

        if (! $branch->isVerified()) {
            $this->components->error(
                "#{$branch->name} is not verified. Seed examples with `php artisan db:seed --class=ExampleBranchesSeeder`, or verify the branch first.",
            );

            return self::FAILURE;
        }

        $branch = $action->handle($branch);
        $this->lineDetail($branch);

        return self::SUCCESS;
    }

    private function refreshVerified(RefreshBranchSiteMeta $action): int
    {
        $branches = Branch::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_domain')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            $this->components->warn(
                'No verified branches to refresh. Seed examples with `php artisan db:seed --class=ExampleBranchesSeeder`.',
            );

            return self::SUCCESS;
        }

        foreach ($branches as $branch) {
            $branch = $action->handle($branch);
            $this->lineDetail($branch);
        }

        $this->components->info("Refreshed site meta for {$branches->count()} verified branches.");

        return self::SUCCESS;
    }

    private function lineDetail(Branch $branch): void
    {
        $meta = mb_trim(implode(' · ', array_filter([
            $branch->brand_color,
            $branch->og_image_url ? 'og:image' : null,
            filled($branch->description) || filled($branch->domain_description) ? 'description' : null,
            $branch->verified_domain,
        ], fn (?string $part): bool => filled($part))));

        $this->components->twoColumnDetail("#{$branch->name}", $meta !== '' ? $meta : 'no site meta');
    }
}
