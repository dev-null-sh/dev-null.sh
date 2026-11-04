<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Headline;
use Carbon\CarbonInterface;

final readonly class HeadlineSummaryCommitsQuery
{
    /**
     * One strongest public root per author, capped to the hottest hundred, with the newest hundred public replies
     * across those threads. Replies stay with their root in chronological order, without account or commit ids.
     * Keys stay internal for the publication recheck, including changes to reply context. Sorting keys makes the
     * snapshot independent of score changes that merely reorder the same sources.
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

        $roots = Commit::query()->whereIn('id', $sources)
            ->orderByDesc('hot_score')->orderByDesc('id')->limit(100)
            ->get(['id', 'body']);
        $replies = Commit::query()->approved()->whereIn('root_id', $roots->modelKeys())
            ->where('body', '!=', '')->where('created_at', '<=', $until)
            ->latest()->latest('id')->limit(100)->get(['id', 'root_id', 'body'])
            ->reverse()->groupBy('root_id');

        return $roots->mapWithKeys(function (Commit $commit) use ($replies): array {
            $context = $replies->get($commit->id, collect())->pluck('body')->all();

            return [$commit->id => $context === [] ? $commit->body
                : json_encode(['post' => $commit->body, 'replies' => $context], JSON_THROW_ON_ERROR)];
        })->sortKeys()->all();
    }
}
