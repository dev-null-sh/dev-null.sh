<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Headline;
use Carbon\CarbonInterface;

final readonly class HeadlineSummaryCommitsQuery
{
    /**
     * One strongest public post per author, capped to the hottest hundred, so repeated posts cannot dominate the
     * recap. Keys stay internal for the publication recheck; providers receive only the bodies. Sorting keys makes
     * the snapshot independent of score changes that merely reorder the same sources.
     *
     * @return array<string, string>
     */
    public function get(Headline $headline, CarbonInterface $until): array
    {
        $sources = Commit::query()->topLevel()->approved()->whereAttachedTo($headline, 'headlines')
            ->where('body', '!=', '')
            ->whereBetween('created_at', [$until->copy()->subHours(8), $until])
            ->select('commits.id')->distinct('commits.author_id')
            ->orderBy('commits.author_id')->orderByDesc('hot_score')->orderByDesc('id');

        return Commit::query()->whereIn('id', $sources)
            ->orderByDesc('hot_score')->orderByDesc('id')->limit(100)
            ->get(['id', 'body'])->mapWithKeys(fn (Commit $commit): array => [$commit->id => $commit->body])
            ->sortKeys()->all();
    }
}
