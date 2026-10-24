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
 * @param  list<array{number: int, title: string, merged_at: string|null}>  $pullRequests
 */
function fakeMergedPullRequests(array $pullRequests): void
{
    Http::fake(['api.github.com/repos/'.config()->string('services.github.repository').'/pulls*' => Http::response(array_map(fn (array $pullRequest): array => [
        ...$pullRequest,
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$pullRequest['number']}",
        'updated_at' => $pullRequest['merged_at'] ?? '2026-10-01T00:00:00Z',
        'merge_commit_sha' => mb_str_pad((string) $pullRequest['number'], 40, 'a'),
        'user' => ['id' => 1, 'login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
    ], $pullRequests))]);
}

it('drafts an email from what was merged in the time it looks back over', function (string $draft, array $included, array $excluded): void {
    fakeMergedPullRequests([
        ['number' => 3, 'title' => 'feat: email everyone', 'merged_at' => '2026-10-23T10:00:00Z'],
        ['number' => 2, 'title' => 'docs: closed without merging', 'merged_at' => null],
        ['number' => 1, 'title' => 'fix: older fix', 'merged_at' => '2026-10-10T10:00:00Z'],
    ]);
    EmailWriter::fake([['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]])->preventStrayPrompts();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => $draft])
        ->assertOk()
        ->assertExactJson(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);

    EmailWriter::assertPrompted(fn ($prompt): bool => collect($included)->every(fn (string $line): bool => str_contains($prompt->prompt, $line))
        && collect($excluded)->every(fn (string $line): bool => ! str_contains($prompt->prompt, $line)));
})->with([
    'weekly' => ['weekly', ['- [feat] Email everyone (#3, https://github.com/dev-null-sh/dev-null.sh/pull/3) by octocat'], ['Older fix', 'Closed without merging']],
    'monthly' => ['monthly', ['Email everyone', '- [fix] Older fix (#1'], ['Closed without merging']],
]);

it('tells the writer when nothing was merged', function (): void {
    fakeMergedPullRequests([]);
    EmailWriter::fake([['subject' => 'Quiet week', 'body' => 'Nothing shipped.']]);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertOk();

    EmailWriter::assertPrompted('No pull requests were merged.');
});

it('fails when the changelog can not be read', function (): void {
    Http::fake(['api.github.com/*' => Http::response(status: 500)]);
    EmailWriter::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
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
