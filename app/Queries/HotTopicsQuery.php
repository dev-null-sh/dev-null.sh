<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class HotTopicsQuery
{
    public function __construct(private HotHeadlinesQuery $headlines, private HeadlineSummaryQuery $summaries)
    {
        //
    }

    /**
     * The hot headlines, each with its recap while it's fresh and safe to show.
     *
     * @return list<array{id: string, name: string, summary: string|null}>
     */
    public function get(User $viewer): array
    {
        $topics = $this->headlines->get($viewer);
        $summaries = $this->summaries->getMany(array_column($topics, 'id'), $viewer);

        return array_map(fn (array $headline): array => [
            'id' => $headline['id'],
            'name' => $headline['name'],
            'summary' => $summaries->get($headline['id']),
        ], $topics);
    }
}
