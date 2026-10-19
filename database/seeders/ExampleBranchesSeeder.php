<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\RefreshTagSiteMeta;
use App\Enums\TagRole;
use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Verified example branches for local Docker / demos.
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
    ];

    public function __construct(private readonly RefreshTagSiteMeta $refreshSiteMeta)
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
        $tag = Tag::query()->updateOrCreate(
            ['name' => $name],
            [
                'domain' => $domain,
                'verified_domain' => $domain,
                'verified_at' => now(),
                'verification_token' => Str::random(32),
                'verification_requested_at' => null,
            ],
        );

        $tag = $this->refreshSiteMeta->handle($tag);
        $this->seedMembers($tag, $team);

        foreach ($team->take(3) as $author) {
            Commit::factory()->for($author)->create([
                'body' => "Shipping on {$name}",
                'branch' => $name,
                'created_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
            ]);
        }

        $this->command?->info(
            "{$name} ← {$domain}".($tag->brand_color ? " ({$tag->brand_color})" : '').
            ' · '.$tag->members()->count().' members',
        );
    }

    /**
     * Two or three maintainers on every branch; larger projects also get a contributor.
     *
     * @param  Collection<int, User>  $team
     */
    private function seedMembers(Tag $tag, Collection $team): void
    {
        $tag->members()->delete();

        $maintainers = $team->take(match ($tag->name) {
            'laravel', 'rails', 'docker' => 3,
            default => 2,
        });

        foreach ($maintainers as $user) {
            $tag->members()->create([
                'user_id' => $user->id,
                'role' => TagRole::Maintainer,
            ]);
        }

        if (in_array($tag->name, ['laravel', 'rails', 'tailwind', 'vercel'], true)) {
            $contributor = $team->skip($maintainers->count())->first();

            if ($contributor instanceof User) {
                $tag->members()->create([
                    'user_id' => $contributor->id,
                    'role' => TagRole::Contributor,
                ]);
            }
        }
    }
}
