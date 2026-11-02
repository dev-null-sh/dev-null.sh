<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

final readonly class HeadlinePageQuery
{
    public function __construct(private CommitSearchQuery $search)
    {
        //
    }

    /**
     * @return array{name: string, summary: Closure(): (string|null), commits: Closure(): Collection<int, Commit>}
     */
    public function get(Headline $headline, User $viewer): array
    {
        return [
            'name' => $headline->name,
            'summary' => function () use ($headline, $viewer): ?string {
                if ($headline->summary === null || ! $headline->summary_created_at?->gt(now()->subHours(Headline::SUMMARY_HOURS))) {
                    return null;
                }

                // Without stored provenance, check all linked posts rather than risk repeating a hidden author's words.
                $hasHiddenSources = $headline->commits()->topLevel()->approved()
                    ->whereNotIn('commits.id', $headline->commits()->select('commits.id')->visibleTo($viewer)->withoutMutedAuthorsFor($viewer))
                    ->exists();

                return $hasHiddenSources ? null : $headline->summary;
            },
            'commits' => fn (): Collection => $this->search->get($headline->name, $viewer, since: now()->subWeek()),
        ];
    }
}
