<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Services\PullRequestTitles;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-import-type Commit from GitHubProvider as GitHubCommit
 *
 * @phpstan-type ChangelogCommit array{sha: string, url: string, pull_request: array{number: int, url: string}|null, type: string|null, breaking: bool, title: string, committed_at: string, author: array{login: string, avatar: string, handle: string|null, mention: string|null, lane: int|string|null}}
 */
final readonly class ChangelogQuery
{
    public const string Branch = 'production';

    private const int PerPage = 100;

    private const int MaxPages = 10;

    private const int Days = 3;

    public function __construct(private GitHubProvider $github, private PullRequestTitles $titles)
    {
        //
    }

    /**
     * The commits that landed on production on the three latest days with commits before the given moment, in the
     * reader's time zone, newest first, and where to carry on from for the three days before them.
     *
     * Only the first-parent line of production counts: the commits of a merged branch arrive with its merge commit.
     * GitHub lists every commit reachable from the branch, most recently committed first, so once a page reaches
     * commits from before a day began, every commit of that day has been seen. Pages are read until that is true of
     * the third day, up to ten pages a time.
     *
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}|null
     */
    public function get(string $timeZone, ?CarbonImmutable $before = null): ?array
    {
        /** @var array<string, GitHubCommit> $loaded */
        $loaded = [];
        $shipped = [];

        for ($page = 1; $page <= self::MaxPages; $page++) {
            $commits = $this->page($page);

            if ($commits === null) {
                return null;
            }

            foreach ($commits as $commit) {
                $loaded[$commit['sha']] = $commit;
            }

            $shipped = array_filter(
                $this->firstParents($loaded),
                fn (array $commit): bool => ! $before instanceof CarbonImmutable || CarbonImmutable::parse($commit['committed_at'])->lessThan($before),
            );

            $days = $this->days($shipped, $timeZone);
            $isLastPage = count($commits) < self::PerPage;

            if (count($days) >= self::Days) {
                $start = CarbonImmutable::parse($days[self::Days - 1], $timeZone)->startOfDay();
                $oldestCommit = $commits === [] ? null : CarbonImmutable::parse($commits[count($commits) - 1]['committed_at']);

                if ($isLastPage || $oldestCommit?->lessThan($start)) {
                    $hasOlder = ! $isLastPage || count($days) > self::Days;

                    return $this->result(
                        array_filter($shipped, fn (array $commit): bool => ! CarbonImmutable::parse($commit['committed_at'])->lessThan($start)),
                        $hasOlder ? $start : null,
                    );
                }
            } elseif ($isLastPage) {
                return $this->result($shipped, null);
            }
        }

        $oldest = min(array_map(fn (array $commit): string => $commit['committed_at'], $shipped) ?: [null]);

        return $this->result($shipped, $oldest === null ? null : CarbonImmutable::parse($oldest));
    }

    /**
     * One page of the commits on production, most recently committed first. Each page is cached for half an hour, and
     * a failed read is not cached, so the next request tries again.
     *
     * @return list<GitHubCommit>|null
     */
    private function page(int $page): ?array
    {
        $repository = config()->string('services.github.repository');

        /** @var list<GitHubCommit>|null */
        return Cache::remember("changelog:{$repository}:commits:page:{$page}", 1800, function () use ($repository, $page): ?array {
            try {
                return $this->github->commits($repository, self::Branch, $page, self::PerPage);
            } catch (HttpClientException) {
                return null;
            }
        });
    }

    /**
     * The line of production from its latest commit back through each first parent, as far as the loaded pages go.
     *
     * @param  array<string, GitHubCommit>  $loaded
     * @return list<GitHubCommit>
     */
    private function firstParents(array $loaded): array
    {
        $line = [];
        $sha = array_key_first($loaded);

        while ($sha !== null && isset($loaded[$sha])) {
            $line[] = $loaded[$sha];
            $sha = $loaded[$sha]['parents'][0] ?? null;
        }

        return $line;
    }

    /**
     * The days with commits in the reader's time zone, latest first.
     *
     * @param  array<int, GitHubCommit>  $commits
     * @return list<string>
     */
    private function days(array $commits, string $timeZone): array
    {
        $days = array_unique(array_map(
            fn (array $commit): string => CarbonImmutable::parse($commit['committed_at'])->setTimezone($timeZone)->toDateString(),
            array_values($commits),
        ));

        rsort($days);

        return $days;
    }

    /**
     * Authors who are on /dev/null keep the lane they chose there, and link to their profile. They are found by their
     * GitHub account, since a handle can change on either side.
     *
     * @param  array<int, GitHubCommit>  $commits
     * @return array{commits: list<ChangelogCommit>, olderBefore: string|null}
     */
    private function result(array $commits, ?CarbonImmutable $olderBefore): array
    {
        $users = User::query()
            ->whereIn('github_id', array_unique(array_filter(array_map(fn (array $commit): ?string => $commit['author']['id'] === null ? null : (string) $commit['author']['id'], $commits))))
            ->get()
            ->keyBy('github_id');

        $changelog = [];

        foreach ($commits as $commit) {
            $headline = $this->headline($commit);

            if ($headline === null) {
                continue;
            }

            $changelog[] = [
                'sha' => $commit['sha'],
                'url' => $commit['url'],
                'pull_request' => $headline['pull_request'],
                ...$this->titles->parse($headline['title']),
                'committed_at' => $commit['committed_at'],
                'author' => $this->author($commit['author'], $commit['author']['id'] === null ? null : $users->get((string) $commit['author']['id'])),
            ];
        }

        return [
            'commits' => $changelog,
            'olderBefore' => $olderBefore?->utc()->toIso8601String(),
        ];
    }

    /**
     * The title of the commit and the pull request it came from. A squashed pull request ends its subject with
     * "(#123)". A merge commit carries the title of its pull request on the line after its subject, and a merge with
     * no title, such as "Merge branch 'main'", is left out.
     *
     * @param  GitHubCommit  $commit
     * @return array{title: string, pull_request: array{number: int, url: string}|null}|null
     */
    private function headline(array $commit): ?array
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $commit['message'])), fn (string $line): bool => $line !== ''));
        $subject = $lines[0] ?? '';

        if (count($commit['parents']) > 1) {
            if (! isset($lines[1])) {
                return null;
            }

            preg_match('/^Merge pull request #(\d+)/', $subject, $matches);

            return ['title' => $lines[1], 'pull_request' => isset($matches[1]) ? $this->pullRequest((int) $matches[1]) : null];
        }

        if (preg_match('/^(.+) \(#(\d+)\)$/', $subject, $matches) === 1) {
            return ['title' => $matches[1], 'pull_request' => $this->pullRequest((int) $matches[2])];
        }

        return ['title' => $subject, 'pull_request' => null];
    }

    /**
     * @return array{number: int, url: string}
     */
    private function pullRequest(int $number): array
    {
        return ['number' => $number, 'url' => 'https://github.com/'.config()->string('services.github.repository').'/pull/'.$number];
    }

    /**
     * @param  array{id: int|null, login: string, avatar_url: string}  $author
     * @return array{login: string, avatar: string, handle: string|null, mention: string|null, lane: int|string|null}
     */
    private function author(array $author, ?User $user): array
    {
        return [
            'login' => $author['login'],
            'avatar' => $author['avatar_url'],
            'handle' => $user?->handle,
            'mention' => $user?->mention(),
            'lane' => $user?->lane(),
        ];
    }
}
