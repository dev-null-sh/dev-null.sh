<?php

declare(strict_types=1);

use App\Ai\Agents\EmailWriter;
use App\Services\EmailDraftProviders\OpenCodeEmailDraftProvider;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function commitForEmail(array $overrides = []): array
{
    return [
        'sha' => str_repeat('a', 40),
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.str_repeat('a', 40),
        'pull_request' => ['number' => 3, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/3'],
        'type' => 'feat',
        'breaking' => false,
        'title' => 'Email everyone',
        'committed_at' => '2026-10-23T10:00:00Z',
        'author' => ['login' => 'octocat', 'avatar' => 'https://avatars.githubusercontent.com/u/1', 'handle' => null, 'mention' => null, 'lane' => null],
        ...$overrides,
    ];
}

it('lists each commit for the writer, linked to its pull request when it has one, and returns its draft', function (): void {
    EmailWriter::fake([['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]])->preventStrayPrompts();

    $draft = new OpenCodeEmailDraftProvider()->draft(7, [
        commitForEmail(),
        commitForEmail(['pull_request' => null, 'type' => null, 'title' => 'Older fix', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.str_repeat('b', 40), 'author' => ['login' => 'alice', 'avatar' => '', 'handle' => 'alice', 'mention' => '@alice', 'lane' => null]]),
    ]);

    expect($draft)->toBe(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);
    EmailWriter::assertPrompted("- [feat] Email everyone (https://github.com/dev-null-sh/dev-null.sh/pull/3) by octocat\n"
        .'- [other] Older fix (https://github.com/dev-null-sh/dev-null.sh/commit/'.str_repeat('b', 40).') by @alice');
});

it('tells the writer when nothing landed', function (): void {
    EmailWriter::fake([['subject' => 'Quiet week', 'body' => 'Nothing shipped.']]);

    expect(new OpenCodeEmailDraftProvider()->draft(7, []))->toBe(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);
    EmailWriter::assertPrompted('Nothing landed on production.');
});

it('has an empty subject and body when the writer leaves them out', function (): void {
    EmailWriter::fake([[]]);

    expect(new OpenCodeEmailDraftProvider()->draft(30, []))->toBe(['subject' => '', 'body' => '']);
});
