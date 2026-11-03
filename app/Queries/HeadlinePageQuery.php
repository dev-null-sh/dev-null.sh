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
    public function __construct(private CommitSearchQuery $search, private HeadlineSummaryQuery $summary)
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
            'summary' => fn (): ?string => $this->summary->get($headline, $viewer),
            'commits' => fn (): Collection => $this->search->get($headline->name, $viewer, since: now()->subWeek()),
        ];
    }
}
