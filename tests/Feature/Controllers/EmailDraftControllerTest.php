<?php

declare(strict_types=1);

use App\Ai\Agents\EmailWriter;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
    $this->travelTo('2026-10-24 12:00:00');
});

/**
 * Production's history, newest first, each commit the first parent of the one before it.
 *
 * @param  list<array{sha: string, message: string, committed_at: string}>  $commits
 */
function fakeProductionCommits(array $commits): void
{
    Http::fake(['api.github.com/repos/'.config()->string('services.github.repository').'/commits*' => Http::response(array_map(fn (array $commit, int $index): array => [
        'sha' => $commit['sha'],
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/commit/{$commit['sha']}",
        'commit' => ['message' => $commit['message'], 'committer' => ['date' => $commit['committed_at']]],
        'author' => ['id' => 1, 'login' => 'octocat', 'type' => 'User', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
        'committer' => ['id' => 1, 'login' => 'octocat', 'type' => 'User', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
        'parents' => isset($commits[$index + 1]) ? [['sha' => $commits[$index + 1]['sha']]] : [],
    ], $commits, array_keys($commits)))]);
}

it('drafts an email from what was committed in the time it looks back over', function (string $draft, array $included, array $excluded): void {
    fakeProductionCommits([
        ['sha' => 'c', 'message' => 'feat: email everyone (#3)', 'committed_at' => '2026-10-23T10:00:00Z'],
        ['sha' => 'b', 'message' => 'feat: audio in remotes', 'committed_at' => '2026-10-21T10:00:00Z'],
        ['sha' => 'a', 'message' => 'fix: older fix (#1)', 'committed_at' => '2026-10-10T10:00:00Z'],
    ]);
    EmailWriter::fake([['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]])->preventStrayPrompts();

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => $draft])
        ->assertOk()
        ->assertExactJson(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);

    EmailWriter::assertPrompted(fn ($prompt): bool => collect($included)->every(fn (string $line): bool => str_contains($prompt->prompt, $line))
        && collect($excluded)->every(fn (string $line): bool => ! str_contains($prompt->prompt, $line)));
})->with([
    'weekly' => ['weekly', ['- [feat] Email everyone (#3, https://github.com/dev-null-sh/dev-null.sh/pull/3) by octocat', '- [feat] Audio in remotes (https://github.com/dev-null-sh/dev-null.sh/commit/b) by octocat'], ['Older fix']],
    'monthly' => ['monthly', ['Email everyone', 'Audio in remotes', '- [fix] Older fix (#1'], []],
]);

it('tells the writer when nothing was committed', function (): void {
    fakeProductionCommits([]);
    EmailWriter::fake([['subject' => 'Quiet week', 'body' => 'Nothing shipped.']]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertOk();

    EmailWriter::assertPrompted('Nothing was committed.');
});

it('fails when the changelog can not be read', function (): void {
    Http::fake(['api.github.com/*' => Http::response(status: 500)]);
    EmailWriter::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertInternalServerError();

    EmailWriter::assertNeverPrompted();
});

it('does not draft emails for non admins', function (): void {
    EmailWriter::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertNotFound();
});
