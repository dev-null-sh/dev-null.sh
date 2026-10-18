<?php

declare(strict_types=1);

use App\Services\PullRequestTitles;

it('splits a conventional title into its type and a capitalised description', function (string $title, string $type, bool $isBreaking, string $description): void {
    expect(resolve(PullRequestTitles::class)->parse($title))
        ->toBe(['type' => $type, 'breaking' => $isBreaking, 'title' => $description]);
})->with([
    'feature' => ['feat: turn emoji shortcodes into emoji', 'feat', false, 'Turn emoji shortcodes into emoji'],
    'fix' => ['fix: prevent duplicate notifications', 'fix', false, 'Prevent duplicate notifications'],
    'every allowed type' => ['revert: undo the search change', 'revert', false, 'Undo the search change'],
    'breaking change' => ['feat!: remove the old API', 'feat', true, 'Remove the old API'],
    'uppercase type' => ['Fix: Restore focus after canceling', 'fix', false, 'Restore focus after canceling'],
    'legacy scope' => ['feat(search): rank exact matches first', 'feat', false, 'Rank exact matches first'],
    'legacy breaking scope' => ['refactor(api)!: drop v1', 'refactor', true, 'Drop v1'],
]);

it('keeps any other title as it was written, without guessing a type', function (string $title): void {
    expect(resolve(PullRequestTitles::class)->parse($title))
        ->toBe(['type' => null, 'breaking' => false, 'title' => $title]);
})->with([
    'plain sentence' => ['Make semantic search more conversational and inclusive'],
    'leading verb that matches a type' => ['Fix GitHub website URLs with uppercase schemes'],
    'unknown type' => ['wip: something unknown'],
    'no space after the colon' => ['feat:missing space'],
    'empty description' => ['feat: '],
]);
