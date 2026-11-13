<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\ChangelogWriter;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Queries\ChangelogQuery;
use App\Queries\TomQuery;
use App\Services\PullRequestTitles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @phpstan-import-type Commit from GitHubProvider as GitHubCommit
 */
final readonly class CreateTomChangelogCommits
{
    public const string LastAnnouncedKey = 'tom:changelog:last-announced-sha';

    private const int PerPage = 100;

    private const int MaxDescriptionLength = 4000;

    private const int MaxExplanationLength = 600;

    public function __construct(
        private GitHubProvider $github,
        private TomQuery $tomQuery,
        private PullRequestTitles $titles,
        private CreateCommit $createCommit,
    ) {
        //
    }

    /**
     * Tom posts one commit for every commit that landed on production since the last one he posted about, oldest
     * first. Only the first-parent line of production counts, as on the changelog: the commits of a merged branch
     * arrive with its merge commit. The first run, or one that can't find where the last left off in the latest
     * hundred commits, only remembers where production is now, so Tom never floods the log with old changes.
     *
     * Where he got to is saved after every post, so a failure picks up from the commit it failed on.
     */
    public function handle(): void
    {
        $tom = $this->tomQuery->get();

        if (! $tom instanceof User) {
            return;
        }

        $repository = config()->string('services.github.repository');
        $line = $this->firstParents($this->github->commits($repository, ChangelogQuery::Branch, 1, self::PerPage));

        if ($line === []) {
            return;
        }

        $last = Cache::get(self::LastAnnouncedKey);
        $position = is_string($last) ? array_search($last, array_column($line, 'sha'), true) : false;

        if ($position === false) {
            if (is_string($last)) {
                Log::warning('Tom could not find the last production commit he announced, so he starts again from the latest one.', ['sha' => $last]);
            }

            Cache::forever(self::LastAnnouncedKey, $line[0]['sha']);

            return;
        }

        foreach (array_reverse(array_slice($line, 0, $position)) as $commit) {
            $this->announce($tom, $repository, $commit);

            Cache::forever(self::LastAnnouncedKey, $commit['sha']);
        }
    }

    /**
     * @param  GitHubCommit  $commit
     */
    private function announce(User $tom, string $repository, array $commit): void
    {
        $headline = $this->titles->ofCommit($commit['message'], count($commit['parents']));

        if ($headline === null) {
            return;
        }

        $number = $headline['pull_request'];
        $description = $number === null ? null : $this->github->pullRequestDescription($repository, $number);

        $explanation = mb_trim(new ChangelogWriter()->prompt(json_encode([
            ...$this->titles->parse($headline['title']),
            'description' => $description === null ? null : Str::limit($description, self::MaxDescriptionLength),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))->text);

        if ($explanation === '') {
            throw new RuntimeException('Tom returned an empty changelog commit.');
        }

        $url = $number === null ? $commit['url'] : ChangelogQuery::pullRequest($number)['url'];

        $this->createCommit->handle($tom, Str::limit($explanation, self::MaxExplanationLength)."\n\n".$this->credit($commit['author']).' '.$url);
    }

    /**
     * Authors who are on /dev/null are mentioned by their handle there, found by their GitHub account since a handle
     * can change on either side. Anyone else keeps their GitHub login, without an @ that would point at someone else.
     *
     * @param  array{id: int|null, login: string, avatar_url: string}  $author
     */
    private function credit(array $author): string
    {
        $user = $author['id'] === null ? null : User::query()->firstWhere('github_id', (string) $author['id']);

        return 'By '.($user instanceof User ? $user->mention() : $author['login']).':';
    }

    /**
     * The line of production from its latest commit back through each first parent, as far as the page goes.
     *
     * @param  list<GitHubCommit>  $commits
     * @return list<GitHubCommit>
     */
    private function firstParents(array $commits): array
    {
        $loaded = array_column($commits, null, 'sha');
        $line = [];
        $sha = $commits[0]['sha'] ?? null;

        while ($sha !== null && isset($loaded[$sha])) {
            $line[] = $loaded[$sha];
            $sha = $loaded[$sha]['parents'][0] ?? null;
        }

        return $line;
    }
}
