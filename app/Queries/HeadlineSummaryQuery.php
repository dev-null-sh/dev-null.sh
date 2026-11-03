<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class HeadlineSummaryQuery
{
    public function get(Headline $headline, User $viewer): ?string
    {
        return $this->getMany([$headline->id], $viewer)->get($headline->id);
    }

    /**
     * @param  list<string>  $headlineIds
     * @return Collection<string, string|null>
     */
    public function getMany(array $headlineIds, User $viewer): Collection
    {
        // Without stored provenance, check all linked threads rather than risk repeating a hidden author's words.
        return Headline::query()->whereKey($headlineIds)
            ->whereNotNull('summary')
            ->where('summary_created_at', '>', now()->subHours(Headline::SUMMARY_HOURS))
            ->whereDoesntHave('commits', fn (Builder $query): Builder => $query->topLevel()->approved()
                ->where(fn (Builder $query): Builder => $query
                    ->whereNotIn('commits.id', Commit::query()->select('commits.id')->visibleTo($viewer)->withoutMutedAuthorsFor($viewer))
                    ->orWhereIn('commits.id', Commit::query()->approved()->select('root_id')->whereNotNull('root_id')
                        ->whereNotIn('commits.id', Commit::query()->select('commits.id')->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)))))
            ->get()
            ->mapWithKeys(fn (Headline $headline): array => [$headline->id => $headline->summary]);
    }
}
