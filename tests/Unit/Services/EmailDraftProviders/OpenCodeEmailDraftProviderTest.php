<?php

declare(strict_types=1);

use App\Ai\Agents\EmailWriter;
use App\Services\EmailDraftProviders\OpenCodeEmailDraftProvider;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mergedPullRequestForEmail(array $overrides = []): array
{
    return [
        'number' => 3,
        'type' => 'feat',
        'breaking' => false,
        'title' => 'Email everyone',
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/3',
        'sha' => str_repeat('a', 40),
        'commit_url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.str_repeat('a', 40),
        'merged_at' => '2026-10-23T10:00:00Z',
        'author' => ['login' => 'octocat', 'avatar' => 'https://avatars.githubusercontent.com/u/1', 'handle' => null, 'mention' => null, 'lane' => null],
        ...$overrides,
    ];
}

it('lists each pull request for the writer and returns its draft', function (): void {
    EmailWriter::fake([['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]])->preventStrayPrompts();

    $draft = new OpenCodeEmailDraftProvider()->draft(7, [
        mergedPullRequestForEmail(),
        mergedPullRequestForEmail(['number' => 1, 'type' => null, 'title' => 'Older fix', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/1', 'author' => ['login' => 'alice', 'avatar' => '', 'handle' => 'alice', 'mention' => '@alice', 'lane' => null]]),
    ]);

    expect($draft)->toBe(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);
    EmailWriter::assertPrompted("- [feat] Email everyone (#3, https://github.com/dev-null-sh/dev-null.sh/pull/3) by octocat\n"
        .'- [other] Older fix (#1, https://github.com/dev-null-sh/dev-null.sh/pull/1) by @alice');
});

it('tells the writer when nothing was merged', function (): void {
    EmailWriter::fake([['subject' => 'Quiet week', 'body' => 'Nothing shipped.']]);

    expect(new OpenCodeEmailDraftProvider()->draft(7, []))->toBe(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);
    EmailWriter::assertPrompted('No pull requests were merged.');
});

it('has an empty subject and body when the writer leaves them out', function (): void {
    EmailWriter::fake([[]]);

    expect(new OpenCodeEmailDraftProvider()->draft(30, []))->toBe(['subject' => '', 'body' => '']);
});
