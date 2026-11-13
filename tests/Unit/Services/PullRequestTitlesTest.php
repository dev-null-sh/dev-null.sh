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

it('reads the title of a commit on production and the pull request it came from', function (string $message, int $parents, ?array $expected): void {
    expect(resolve(PullRequestTitles::class)->ofCommit($message, $parents))->toBe($expected);
})->with([
    'squashed pull request' => ['feat: add an emoji picker (#1468)', 1, ['title' => 'feat: add an emoji picker', 'pull_request' => 1468]],
    'squashed with a body' => ["fix: centre the dot (#1544)\n\nIt was off by a pixel.", 1, ['title' => 'fix: centre the dot', 'pull_request' => 1544]],
    'pushed straight' => ['fix: pushed straight to production', 1, ['title' => 'fix: pushed straight to production', 'pull_request' => null]],
    'merged pull request' => ["Merge pull request #310 from simonhamp/hover\n\nfeat: dim other commits on mouse hover", 2, ['title' => 'feat: dim other commits on mouse hover', 'pull_request' => 310]],
    'titled merge without a pull request' => ["Merge remote-tracking branch 'origin/production'\n\nRevert the hover", 2, ['title' => 'Revert the hover', 'pull_request' => null]],
    'untitled merge' => ["Merge branch 'feat/macos-app' into production", 2, null],
]);
