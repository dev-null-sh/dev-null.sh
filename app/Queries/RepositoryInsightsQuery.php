<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

final readonly class RepositoryInsightsQuery
{
    public function __construct(private GitHubProvider $github, private ChangelogQuery $changelog)
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
                $data = match ($metric) {
                    'recent_changes' => $this->recentChanges(),
                    'contributors' => $this->contributors($repository),
                    'opportunities' => $this->opportunities($repository),
                    'overview' => $this->overview($repository),
                    'languages' => [
                        'definition' => 'Repository languages measured in bytes of code, not people or skill.',
                        'complete' => true,
                        'languages' => $this->github->languages($repository),
                    ],
                };
            } catch (HttpClientException) {
                return null;
            }

            if ($data === null) {
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

    /**
     * @return array<string, mixed>|null
     */
    private function recentChanges(): ?array
    {
        $changelog = $this->changelog->get('UTC');

        if ($changelog === null) {
            return null;
        }

        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    private function contributors(string $repository): array
    {
        return [
            'definition' => 'Up to ten top GitHub accounts by GitHub-reported all-time commit count, excluding bots and anonymous contributors. Counts do not measure contribution quality.',
            'complete' => false,
            'coverage' => 'First 100 contributors in GitHub commit-count order, then filtered to users and limited to ten. Not an exhaustive contributor count.',
            'contributors' => collect($this->github->contributors($repository, 1, 100))->where('type', 'User')->take(10)->mapInto(Fluent::class)->map(fn (Fluent $contributor): array => [
                'username' => $contributor->string('login')->value(),
                'commits' => $contributor->integer('contributions'),
                'url' => $contributor->string('html_url')->value(),
            ])->values()->all(),
        ];
    }

    /**
     * Null when GitHub's search didn't finish, so the list may be missing issues.
     *
     * @return array<string, mixed>|null
     */
    private function opportunities(string $repository): ?array
    {
        $results = $this->github->searchIssues('repo:'.$repository.' is:issue is:open label:"good first issue","help wanted"', 10, 'updated');

        if ($results['incomplete']) {
            return null;
        }

        return [
            'definition' => 'Up to ten recently updated open issues labelled good first issue or help wanted.',
            'complete' => false,
            'coverage' => 'A limited issue list, not all available work or a commitment that an issue is unassigned.',
            'issues' => collect($results['items'])->mapInto(Fluent::class)->map(fn (Fluent $issue): array => [
                'title' => $issue->string('title')->substr(0, 300)->value(),
                'url' => $issue->string('html_url')->value(),
            ])->all(),
        ];
    }

    /**
     * Null when GitHub's search didn't finish, so a count may be short.
     *
     * @return array<string, mixed>|null
     */
    private function overview(string $repository): ?array
    {
        $data = [
            'definition' => 'Repository stars and forks, with open issues and open pull requests counted separately by GitHub search.',
            'complete' => true,
            ...$this->github->repository($repository),
        ];

        foreach (['open_issues' => 'issue', 'open_pull_requests' => 'pr'] as $key => $type) {
            $results = $this->github->searchIssues("repo:{$repository} is:{$type} is:open", 1, null);

            if ($results['incomplete']) {
                return null;
            }

            $data[$key] = $results['total'];
        }

        return $data;
    }
}
