<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use App\Services\PullRequestTitles;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type GitHubAccount array{id: int, login: string, avatar_url: string, type: string}
 * @phpstan-type GitHubCommit array{sha: string, html_url: string, commit: array{message: string, committer: array{date: string}}, author: GitHubAccount|null, committer: GitHubAccount|null, parents: list<array{sha: string}>}
 * @phpstan-type ChangelogCommit array{sha: string, type: string|null, breaking: bool, title: string, url: string, pull_request: array{number: int, url: string}|null, committed_at: string, author: array{login: string, avatar: string, handle: string|null, mention: string|null, lane: int|string|null}}
 */
final readonly class ChangelogQuery
{
    public const string Branch = 'production';

    private const int PerPage = 100;

    private const int MaxPages = 20;

    private const int Days = 3;

    public function __construct(private GitHub $github, private PullRequestTitles $titles)
    {
        //
    }

    /**
     * The commits on production on the three latest days with commits before the given moment, in the reader's time
     * zone, newest first, and where to carry on from for the three days before them.
     *
     * Production's own line runs from its newest commit through each commit's first parent, so the commits of a branch
     * merged into it are left out, and so is the merge of a branch, which says nothing of its own. The line is read a
     * hundred commits at a time from wherever it has got to, up to twenty pages a time.
     *
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}|null
     */
    public function get(string $timeZone, ?CarbonImmutable $before = null): ?array
    {
        /** @var array<string, GitHubCommit> $loaded */
        $loaded = [];
        $line = [];
        $days = [];
        $next = null;

        for ($page = 1; $page <= self::MaxPages; $page++) {
            $commits = $this->page($next ?? self::Branch);

            if ($commits === null) {
                return null;
            }

            foreach ($commits as $commit) {
                $loaded[$commit['sha']] = $commit;
            }

            $next ??= $commits[0]['sha'] ?? null;

            while ($next !== null && isset($loaded[$next])) {
                $commit = $loaded[$next];
                $next = $commit['parents'][0]['sha'] ?? null;
                $committedAt = CarbonImmutable::parse($commit['commit']['committer']['date']);
                $author = $this->author($commit);

                if (($before instanceof CarbonImmutable && ! $committedAt->lessThan($before)) || $author === null || $this->isBranchMerge($commit)) {
                    continue;
                }

                $day = $committedAt->setTimezone($timeZone)->startOfDay();
                $days[$day->toDateString()] = $day;

                if (count($days) > self::Days) {
                    return $this->result($line, array_values($days)[self::Days - 1]);
                }

                $line[] = ['commit' => $commit, 'author' => $author];
            }

            if ($next === null) {
                return $this->result($line, null);
            }
        }

        return $this->result($line, $line === [] ? null : CarbonImmutable::parse(end($line)['commit']['commit']['committer']['date']));
    }

    /**
     * A hundred commits of production's history from the given commit, newest first. Each page is cached for half an
     * hour, and a failed read is not cached, so the next request tries again.
     *
     * @return list<GitHubCommit>|null
     */
    private function page(string $sha): ?array
    {
        $repository = config()->string('services.github.repository');

        /** @var list<GitHubCommit>|null */
        return Cache::remember("changelog:{$repository}:commits:{$sha}", 1800, function () use ($repository, $sha): ?array {
            try {
                $commits = $this->github->request()->get("repos/{$repository}/commits", [
                    'sha' => $sha,
                    'per_page' => self::PerPage,
                ])->throw()->json();
            } catch (HttpClientException) {
                return null;
            }

            return is_array($commits) && array_is_list($commits) ? $commits : null;
        });
    }

    /**
     * The person a commit is shown under: its author, or whoever committed it for an agent or a bot. A commit with no
     * person on GitHub behind it is left out.
     *
     * @param  GitHubCommit  $commit
     * @return GitHubAccount|null
     */
    private function author(array $commit): ?array
    {
        foreach ([$commit['author'], $commit['committer']] as $account) {
            if ($account !== null && $account['type'] === 'User' && ! in_array(mb_strtolower($account['login']), [...ContributorsQuery::Agents, 'web-flow'], true)) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @param  GitHubCommit  $commit
     */
    private function isBranchMerge(array $commit): bool
    {
        return count($commit['parents']) > 1 && ! str_starts_with($commit['commit']['message'], 'Merge pull request #');
    }

    /**
     * Commits from a pull request link to it: a squash merge ends its subject with the number, and a merge commit names
     * it, with the pull request's title underneath. Authors who are on /dev/null keep the lane they chose there, and
     * link to their profile. They are found by their GitHub account, since a handle can change on either side.
     *
     * @param  list<array{commit: GitHubCommit, author: GitHubAccount}>  $line
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}
     */
    private function result(array $line, ?CarbonImmutable $olderBefore): array
    {
        $repository = config()->string('services.github.repository');

        $users = User::query()
            ->whereIn('github_id', array_unique(array_map(fn (array $entry): string => (string) $entry['author']['id'], $line)))
            ->get()
            ->keyBy('github_id');

        return [
            'commits' => array_map(function (array $entry) use ($repository, $users): array {
                ['commit' => $commit, 'author' => $author] = $entry;
                $lines = explode("\n", $commit['commit']['message']);
                $title = $lines[0];
                $number = null;

                if (preg_match('/^Merge pull request #(\d+) /', $title, $matches) === 1) {
                    $number = (int) $matches[1];
                    $title = mb_trim($lines[2] ?? '') ?: $title;
                } elseif (preg_match('/^(.+) \(#(\d+)\)$/', $title, $matches) === 1) {
                    $title = $matches[1];
                    $number = (int) $matches[2];
                }

                $user = $users->get((string) $author['id']);

                return [
                    'sha' => $commit['sha'],
                    ...$this->titles->parse($title),
                    'url' => $commit['html_url'],
                    'pull_request' => $number === null ? null : ['number' => $number, 'url' => "https://github.com/{$repository}/pull/{$number}"],
                    'committed_at' => $commit['commit']['committer']['date'],
                    'author' => [
                        'login' => $author['login'],
                        'avatar' => $author['avatar_url'],
                        'handle' => $user?->handle,
                        'mention' => $user?->mention(),
                        'lane' => $user?->lane(),
                    ],
                ];
            }, $line),
            'olderBefore' => $olderBefore?->utc()->toIso8601String(),
        ];
    }
}
