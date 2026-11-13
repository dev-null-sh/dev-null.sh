<?php

declare(strict_types=1);

namespace App\Services;

final readonly class PullRequestTitles
{
    private const array Types = ['feat', 'fix', 'docs', 'style', 'refactor', 'perf', 'test', 'build', 'ci', 'chore', 'revert'];

    /**
     * Splits a conventional title such as "feat!: remove the old API" into its type, whether it is breaking, and its
     * description. Titles are unscoped from now on, but older ones may still carry a scope, which is dropped. Any other
     * title is kept as written, with no type guessed from its wording.
     *
     * @return array{type: string|null, breaking: bool, title: string}
     */
    public function parse(string $title): array
    {
        if (preg_match('/^([a-z]+)(?:\([^)]*\))?(!)?: (\S.*)$/i', $title, $matches) === 1 && in_array(mb_strtolower($matches[1]), self::Types, true)) {
            return ['type' => mb_strtolower($matches[1]), 'breaking' => $matches[2] === '!', 'title' => ucfirst($matches[3])];
        }

        return ['type' => null, 'breaking' => false, 'title' => $title];
    }

    /**
     * The title of a commit on production and the number of the pull request it came from. A squashed pull request
     * ends its subject with "(#123)". A merge commit carries the title of its pull request on the line after its
     * subject, and a merge with no title, such as "Merge branch 'main'", has none.
     *
     * @return array{title: string, pull_request: int|null}|null
     */
    public function ofCommit(string $message, int $parents): ?array
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $message)), fn (string $line): bool => $line !== ''));
        $subject = $lines[0] ?? '';

        if ($parents > 1) {
            if (! isset($lines[1])) {
                return null;
            }

            preg_match('/^Merge pull request #(\d+)/', $subject, $matches);

            return ['title' => $lines[1], 'pull_request' => isset($matches[1]) ? (int) $matches[1] : null];
        }

        if (preg_match('/^(.+) \(#(\d+)\)$/', $subject, $matches) === 1) {
            return ['title' => $matches[1], 'pull_request' => (int) $matches[2]];
        }

        return ['title' => $subject, 'pull_request' => null];
    }
}
