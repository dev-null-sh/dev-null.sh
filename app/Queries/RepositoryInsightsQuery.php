<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

final readonly class RepositoryInsightsQuery
{
    public function __construct(private GitHub $github, private ChangelogQuery $changelog)
    {
        //
    }

    /**
     * @param  'contributors'|'recent_changes'|'opportunities'|'overview'|'languages'  $metric
     * @return array<string, mixed>|null
     */
    public function get(string $metric): ?array
    {
        $repository = config()->string('services.github.repository');

        return Cache::remember("repository-insights:{$repository}:{$metric}", 900, function () use ($repository, $metric): ?array {
            try {
                if ($metric === 'recent_changes') {
                    $changelog = $this->changelog->get('UTC');

                    if ($changelog === null) {
                        return null;
                    }

                    $data = [
                        'definition' => 'Up to ten recent pull requests merged into production, from the changelog. Merge time is not a verified deployment time.',
                        'complete' => false,
                        'coverage' => 'Changelog scans at most 1,000 closed PRs for the three latest UTC days with merges. This list is not an exhaustive count or guaranteed latest under truncation.',
                        'pull_requests' => collect($changelog['pullRequests'])->take(10)->map(fn (array $pullRequest): array => [
                            'title' => mb_substr($pullRequest['title'], 0, 300),
                            'url' => $pullRequest['url'],
                            'author' => $pullRequest['author']['login'],
                            'merged_at' => $pullRequest['merged_at'],
                        ])->all(),
                    ];
                } else {
                    $path = match ($metric) {
                        'opportunities' => 'search/issues',
                        'overview' => "repos/{$repository}",
                        default => "repos/{$repository}/{$metric}",
                    };
                    $parameters = match ($metric) {
                        'contributors' => ['per_page' => 100],
                        'opportunities' => [
                            'q' => 'repo:'.$repository.' is:issue is:open label:"good first issue","help wanted"',
                            'sort' => 'updated', 'order' => 'desc', 'per_page' => 10,
                        ],
                        default => [],
                    };
                    $response = $this->github->request()->get($path, $parameters)->throw();

                    if ($metric === 'opportunities' && $response->fluent()->boolean('incomplete_results')) {
                        return null;
                    }

                    $data = match ($metric) {
                        'contributors' => [
                            'definition' => 'Up to ten top GitHub accounts by GitHub-reported all-time commit count, excluding bots and anonymous contributors. Counts do not measure contribution quality.',
                            'complete' => false,
                            'coverage' => 'First 100 contributors in GitHub commit-count order, then filtered to users and limited to ten. Not an exhaustive contributor count.',
                            'contributors' => $response->collect()->where('type', 'User')->take(10)->mapInto(Fluent::class)->map(fn (Fluent $contributor): array => [
                                'username' => $contributor->string('login')->value(),
                                'commits' => $contributor->integer('contributions'),
                                'url' => $contributor->string('html_url')->value(),
                            ])->values()->all(),
                        ],
                        'opportunities' => [
                            'definition' => 'Up to ten recently updated open issues labelled good first issue or help wanted.',
                            'complete' => false,
                            'coverage' => 'A limited issue list, not all available work or a commitment that an issue is unassigned.',
                            'issues' => $response->collect('items')->mapInto(Fluent::class)->map(fn (Fluent $issue): array => [
                                'title' => $issue->string('title')->substr(0, 300)->value(),
                                'url' => $issue->string('html_url')->value(),
                            ])->all(),
                        ],
                        'overview' => [
                            'definition' => 'Repository stars and forks, with open issues and open pull requests counted separately by GitHub search.',
                            'complete' => true,
                            'stars' => $response->fluent()->integer('stargazers_count'),
                            'forks' => $response->fluent()->integer('forks_count'),
                        ],
                        'languages' => [
                            'definition' => 'Repository languages measured in bytes of code, not people or skill.',
                            'complete' => true,
                            'languages' => $response->json(),
                        ],
                    };

                    if ($metric === 'overview') {
                        foreach (['open_issues' => 'issue', 'open_pull_requests' => 'pr'] as $key => $type) {
                            $count = $this->github->request()->get('search/issues', [
                                'q' => "repo:{$repository} is:{$type} is:open",
                                'per_page' => 1,
                            ])->throw()->fluent();

                            if ($count->boolean('incomplete_results')) {
                                return null;
                            }

                            $data[$key] = $count->integer('total_count');
                        }
                    }
                }
            } catch (HttpClientException) {
                return null;
            }

            return [
                'metric' => $metric,
                'repository' => $repository,
                'source_url' => 'https://github.com/'.$repository,
                'as_of' => now()->toIso8601String(),
                'freshness' => 'Cached for 15 minutes. GitHub contributor counts can lag by hours; changelog pages are cached for another 30 minutes. This is not a live deployment status.',
                ...$data,
            ];
        });
    }
}
