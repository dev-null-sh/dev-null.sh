<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class HashtagSuggestionsQuery
{
    /**
     * Branches from the tags table and from approved top-level commits that start with the typed prefix.
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

        /** @var list<object{name: string, count: int|string}> $tags */
        $tags = DB::select(
            <<<'SQL'
                select name, count(*)::int as count
                from (
                    select commits.branch as name
                    from commits
                    where commits.status = ?
                      and commits.parent_id is null
                      and commits.deleted_at is null
                      and commits.branch is not null
                      and commits.branch like ? escape '!'
                    union all
                    select tags.name
                    from tags
                    where tags.name like ? escape '!'
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

        return array_map(fn (object $tag): array => [
            'name' => $tag->name,
            'count' => (int) $tag->count,
        ], $tags);
    }
}
