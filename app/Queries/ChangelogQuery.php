<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use App\Services\PullRequestTitles;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class ChangelogQuery
{
    public const string Repository = 'nunomaduro/dev-null.sh';

    public const string Branch = 'production';

    public function __construct(private GitHub $github, private PullRequestTitles $titles)
    {
        //
    }

    /**
     * The latest pull requests merged into production, newest merge first. GitHub can't sort by merge date, so the most
     * recently updated closed ones are fetched and sorted here. It is cached for ten minutes, and a failed read is not
     * cached, so the next request tries again.
     *
     * @return list<array{number: int, type: string|null, breaking: bool, title: string, url: string, sha: string, commit_url: string, merged_at: string, author: array{login: string, avatar: string}}>|null
     */
    public function get(): ?array
    {
        /** @var list<array{number: int, type: string|null, breaking: bool, title: string, url: string, sha: string, commit_url: string, merged_at: string, author: array{login: string, avatar: string}}>|null */
        return Cache::remember('changelog', 600, function (): ?array {
            try {
                $pullRequests = $this->github->request()->get('repos/'.self::Repository.'/pulls', [
                    'state' => 'closed',
                    'base' => self::Branch,
                    'sort' => 'updated',
                    'direction' => 'desc',
                    'per_page' => 100,
                ])->throw()->json();
            } catch (HttpClientException) {
                return null;
            }

            if (! is_array($pullRequests) || ! array_is_list($pullRequests)) {
                return null;
            }

            /** @var list<array{number: int, title: string, html_url: string, merged_at: string|null, merge_commit_sha: string, user: array{login: string, avatar_url: string}}> $pullRequests */
            $merged = array_filter($pullRequests, fn (array $pullRequest): bool => $pullRequest['merged_at'] !== null);

            usort($merged, fn (array $a, array $b): int => strcmp((string) $b['merged_at'], (string) $a['merged_at']));

            return array_map(fn (array $pullRequest): array => [
                'number' => $pullRequest['number'],
                ...$this->titles->parse($pullRequest['title']),
                'url' => $pullRequest['html_url'],
                'sha' => $pullRequest['merge_commit_sha'],
                'commit_url' => 'https://github.com/'.self::Repository.'/commit/'.$pullRequest['merge_commit_sha'],
                'merged_at' => (string) $pullRequest['merged_at'],
                'author' => ['login' => $pullRequest['user']['login'], 'avatar' => $pullRequest['user']['avatar_url']],
            ], $merged);
        });
    }
}
