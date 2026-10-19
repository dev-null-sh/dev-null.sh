<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitStatus;
use App\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Branch chrome for commit serialization: brand colour, verification, and commit counts.
 */
final class BranchStyles
{
    /**
     * @var array<string, array{name: string, commits_count: int, authors_count: int, is_verified: bool, brand_color: string|null, verified_domain: string|null, description: string|null, og_image_url: string|null}>|null
     */
    private static ?array $summaries = null;

    /**
     * Hex brand colour for a verified branch name, if any.
     */
    public function color(string $name): ?string
    {
        return $this->summary($name)['brand_color'] ?? null;
    }

    /**
     * @return array{name: string, commits_count: int, authors_count: int, is_verified: bool, brand_color: string|null, verified_domain: string|null, description: string|null, og_image_url: string|null}
     */
    public function summary(string $name): array
    {
        $name = mb_strtolower($name);

        return $this->all()[$name] ?? [
            'name' => $name,
            'commits_count' => 0,
            'authors_count' => 0,
            'is_verified' => false,
            'brand_color' => null,
            'verified_domain' => null,
            'description' => null,
            'og_image_url' => null,
        ];
    }

    public function forget(): void
    {
        self::$summaries = null;
        Cache::forget('branch-styles:v2');
    }

    /**
     * @return array<string, array{name: string, commits_count: int, authors_count: int, is_verified: bool, brand_color: string|null, verified_domain: string|null, description: string|null, og_image_url: string|null}>
     */
    private function all(): array
    {
        return self::$summaries ??= Cache::remember('branch-styles:v2', 60, function (): array {
            /** @var list<object{name: string, commits_count: int|string, authors_count: int|string}> $counts */
            $counts = DB::select(
                <<<'SQL'
                    select commits.branch as name,
                           count(*)::int as commits_count,
                           count(distinct commits.user_id)::int as authors_count
                    from commits
                    where commits.status = ?
                      and commits.parent_id is null
                      and commits.deleted_at is null
                      and commits.branch is not null
                    group by commits.branch
                    SQL,
                [CommitStatus::Approved->value],
            );

            $summaries = [];

            foreach ($counts as $row) {
                $summaries[$row->name] = [
                    'name' => $row->name,
                    'commits_count' => (int) $row->commits_count,
                    'authors_count' => (int) $row->authors_count,
                    'is_verified' => false,
                    'brand_color' => null,
                    'verified_domain' => null,
                    'description' => null,
                    'og_image_url' => null,
                ];
            }

            $names = array_keys($summaries);

            Branch::query()
                ->when(
                    $names !== [],
                    fn ($query) => $query->whereIn('name', $names)->orWhereNotNull('verified_at'),
                    fn ($query) => $query->whereNotNull('verified_at'),
                )
                ->get([
                    'name',
                    'brand_color',
                    'verified_at',
                    'verified_domain',
                    'description',
                    'domain_description',
                    'og_image_url',
                ])
                ->each(function (Branch $branch) use (&$summaries): void {
                    $summaries[$branch->name] ??= [
                        'name' => $branch->name,
                        'commits_count' => 0,
                        'authors_count' => 0,
                        'is_verified' => false,
                        'brand_color' => null,
                        'verified_domain' => null,
                        'description' => null,
                        'og_image_url' => null,
                    ];

                    $summaries[$branch->name]['is_verified'] = $branch->isVerified();
                    $summaries[$branch->name]['brand_color'] = $branch->brand_color;
                    $summaries[$branch->name]['verified_domain'] = $branch->isVerified()
                        ? $branch->verified_domain
                        : null;
                    $summaries[$branch->name]['description'] = $branch->description
                        ?? $branch->domain_description;
                    $summaries[$branch->name]['og_image_url'] = $branch->og_image_url;
                });

            return $summaries;
        });
    }
}
