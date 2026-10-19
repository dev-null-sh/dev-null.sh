<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\RefreshBranchSiteMeta;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Verified example branches for local Docker / demos.
 *
 * Sets ownership and verification, then pulls site meta the same way
 * `php artisan branches:refresh-site-meta` does.
 */
final class ExampleBranchesSeeder extends Seeder
{
    /**
     * @var array<string, string> branch name => domain
     */
    private const array BRANCHES = [
        'docker' => 'docker.com',
        'ruby' => 'ruby-lang.org',
        'rails' => 'rubyonrails.org',
        'vercel' => 'vercel.com',
        'cloudflare' => 'cloudflare.com',
        'astrobuild' => 'astro.build',
        'tailwind' => 'tailwindcss.com',
        'pestphp' => 'pestphp.com',
        'laravel' => 'laravel.com',
        'unihomes' => 'unihomes.co.uk',
    ];

    public function __construct(private readonly RefreshBranchSiteMeta $refreshSiteMeta)
    {
        //
    }

    public function run(): void
    {
        /** @var Collection<int, User> $team */
        $team = User::factory()->count(5)->create();

        foreach (self::BRANCHES as $name => $domain) {
            $this->seedBranch($name, $domain, $team);
        }
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function seedBranch(string $name, string $domain, Collection $team): void
    {
        $branch = Branch::query()->firstOrCreate(
            ['name' => $name],
            ['id' => (string) Str::uuid7()],
        );

        $branch->forceFill([
            'domain' => $domain,
            'verified_domain' => $domain,
            'verified_at' => now(),
            'verification_token' => $branch->verification_token ?? Str::random(32),
            'verification_requested_at' => null,
        ])->save();

        $branch = $this->refreshSiteMeta->handle($branch->refresh());
        $this->seedMembers($branch, $team);

        foreach ($team->take(3) as $author) {
            Commit::factory()->for($author)->create([
                'body' => "Shipping on {$name}",
                'branch' => $name,
                'created_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
            ]);
        }

        $this->command?->info(
            "{$name} ← {$domain}".
            ($branch->brand_color ? " ({$branch->brand_color})" : ' (no brand colour)').
            ($branch->og_image_url ? ' · og:image' : '').
            ' · '.$branch->members()->count().' members',
        );
    }

    /**
     * Two or three maintainers on every branch; larger projects also get a contributor.
     *
     * @param  Collection<int, User>  $team
     */
    private function seedMembers(Branch $branch, Collection $team): void
    {
        $branch->members()->delete();

        $maintainers = $team->take(match ($branch->name) {
            'laravel', 'rails', 'docker' => 3,
            default => 2,
        });

        foreach ($maintainers as $user) {
            $branch->members()->create([
                'user_id' => $user->id,
                'role' => BranchRole::Maintainer,
            ]);
        }

        if (in_array($branch->name, ['laravel', 'rails', 'tailwind', 'vercel'], true)) {
            $contributor = $team->skip($maintainers->count())->first();

            if ($contributor instanceof User) {
                $branch->members()->create([
                    'user_id' => $contributor->id,
                    'role' => BranchRole::Contributor,
                ]);
            }
        }
    }
}
