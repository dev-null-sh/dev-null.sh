<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class BranchSuggestionsQuery
{
    /**
     * Branches from the branches table and from approved top-level commits that start with the typed prefix.
     * Needs at least two characters. Exact matches first, then the most-used names.
     *
     * @return list<array{name: string, count: int}>
     */
    public function get(string $term, int $limit = 6): array
    {
        $term = Str::of($term)
            ->trim()
            ->ltrim('#')
            ->lower()
            ->value();

        if (mb_strlen($term) < 2 || ! preg_match('/^[a-z0-9][a-z0-9_]{0,49}$/', $term)) {
            return [];
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        /** @var list<object{name: string, count: int|string}> $branches */
        $branches = DB::select(
            <<<'SQL'
                select name, sum(weight)::int as count
                from (
                    select commits.branch as name, 1 as weight
                    from commits
                    where commits.status = ?
                      and commits.parent_id is null
                      and commits.deleted_at is null
                      and commits.branch is not null
                      and commits.branch like ? escape '!'
                    union all
                    select branches.name as name, 0 as weight
                    from branches
                    where branches.name like ? escape '!'
                ) as matches
                group by name
                order by
                  case when name = ? then 0 else 1 end,
                  count desc,
                  name asc
                limit ?
                SQL,
            [CommitStatus::Approved->value, "{$escaped}%", "{$escaped}%", $term, $limit],
        );

        return array_map(fn (object $branch): array => [
            'name' => $branch->name,
            'count' => (int) $branch->count,
        ], $branches);
    }
}
