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
}
