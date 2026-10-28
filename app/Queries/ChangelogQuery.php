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

    private const int MaxPages = 10;

    private const int MaxDaysRead = 10;

    private const int Days = 3;

    private const int TodayTtl = 1800; // 30 minutes

    private const int PastTtl = 43200; // 12 hours

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
     * day at a time, newest day first, and a day without commits is skipped by asking for the latest commit before it.
     * Up to ten days are read a time. Where the line goes on is kept with the moment handed back, so the next three
     * days carry on from there instead of reading down from the newest commit again. Without it, the line is picked up
     * at the latest commit of the day before the given moment.
     *
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}|null
     */
    public function get(string $timeZone, ?CarbonImmutable $before = null): ?array
    {
        /** @var array<string, GitHubCommit> $loaded */
        $loaded = [];
        $line = [];
        $days = [];
        $next = $before instanceof CarbonImmutable ? Cache::get($this->resumeKey($before)) : null;
        $next = is_string($next) ? $next : null;

        $start = ($before?->subSecond() ?? CarbonImmutable::now())->setTimezone($timeZone)->startOfDay();

        for ($read = 1; $read <= self::MaxDaysRead; $read++) {
            $commits = $this->day($next ?? self::Branch, $start);

            if ($commits === null) {
                return null;
            }

            if ($commits === []) {
                $latest = $this->read($next ?? self::Branch, $start->subSecond(), ['per_page' => 1]);

                if ($latest === null) {
                    return null;
                }

                if ($latest === []) {
                    return $this->result($line, null);
                }

                $start = CarbonImmutable::parse($latest[0]['commit']['committer']['date'])->setTimezone($timeZone)->startOfDay();

                continue;
            }

            foreach ($commits as $commit) {
                $loaded[$commit['sha']] = $commit;
            }

            $next ??= $commits[0]['sha'];

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
                    return $this->result($line, array_values($days)[self::Days - 1], $commit['sha']);
                }

                $line[] = ['commit' => $commit, 'author' => $author];
            }

            if ($next === null) {
                return $this->result($line, null);
            }

            $start = $start->subDay();
        }

        return $this->result($line, $start->addDay(), $next);
    }

    /**
     * Production's commits made on the day that starts at the given moment, newest first, from the given commit.
     *
     * @return list<GitHubCommit>|null
     */
    private function day(string $sha, CarbonImmutable $start): ?array
    {
        $commits = [];

        for ($page = 1; $page <= self::MaxPages; $page++) {
            $read = $this->read($sha, $start->addDay()->subSecond(), [
                'since' => $start->utc()->toIso8601ZuluString(),
                'per_page' => self::PerPage,
                'page' => $page,
            ]);

            if ($read === null) {
                return null;
            }

            $commits = [...$commits, ...$read];

            if (count($read) < self::PerPage) {
                break;
            }
        }

        return $commits;
    }

    /**
     * Production's commits up to the given moment, newest first, from the given commit. A day that is still going is
     * cached for half an hour, and one that is over for twelve hours. A failed read is not cached, so the next request
     * tries again.
     *
     * @param  array<string, int|string>  $query
     * @return list<GitHubCommit>|null
     */
    private function read(string $sha, CarbonImmutable $until, array $query): ?array
    {
        $repository = config()->string('services.github.repository');
        $query = ['sha' => $sha, 'until' => $until->utc()->toIso8601ZuluString(), ...$query];

        /** @var list<GitHubCommit>|null */
        return Cache::remember("changelog:{$repository}:commits:".http_build_query($query), $until->isFuture() ? self::TodayTtl : self::PastTtl, function () use ($repository, $query): ?array {
            try {
                $commits = $this->github->request()->get("repos/{$repository}/commits", $query)->throw()->json();
            } catch (HttpClientException) {
                return null;
            }

            return is_array($commits) && array_is_list($commits) ? $commits : null;
        });
    }

    private function resumeKey(CarbonImmutable $before): string
    {
        return 'changelog:'.config()->string('services.github.repository').":resume:{$before->getTimestamp()}";
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
     * Where the line goes on from is kept with the moment handed back, for the next three days.
     *
     * @param  list<array{commit: GitHubCommit, author: GitHubAccount}>  $line
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}
     */
    private function result(array $line, ?CarbonImmutable $olderBefore, ?string $resumeFrom = null): array
    {
        $repository = config()->string('services.github.repository');

        if ($olderBefore instanceof CarbonImmutable && $resumeFrom !== null) {
            Cache::put($this->resumeKey($olderBefore), $resumeFrom, self::PastTtl);
        }

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
