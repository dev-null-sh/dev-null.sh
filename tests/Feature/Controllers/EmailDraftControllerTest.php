<?php

declare(strict_types=1);

use App\Contracts\Services\EmailDraftProvider;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
    $this->travelTo('2026-10-24 12:00:00');
});

/**
 * @param  list<array{sha: string, message: string, committed_at: string, parents: list<string>}>  $commits
 * @return list<array<string, mixed>>
 */
function emailDraftCommits(array $commits): array
{
    return array_map(fn (array $commit): array => [
        ...$commit,
        'url' => "https://github.com/dev-null-sh/dev-null.sh/commit/{$commit['sha']}",
        'author' => ['id' => 1, 'login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
    ], $commits);
}

it('drafts an email from what landed in the time it looks back over', function (string $draft, int $days, array $landed): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->with('dev-null-sh/dev-null.sh', 'production', Mockery::any(), Mockery::any())->andReturn(emailDraftCommits([
        ['sha' => 'c3', 'message' => 'feat: email everyone (#3)', 'committed_at' => '2026-10-23T10:00:00Z', 'parents' => ['c1']],
        ['sha' => 'c2', 'message' => 'docs: on a branch production never took', 'committed_at' => '2026-10-22T10:00:00Z', 'parents' => ['c1']],
        ['sha' => 'c1', 'message' => 'fix: older fix', 'committed_at' => '2026-10-10T10:00:00Z', 'parents' => []],
    ]));
    $this->mock(EmailDraftProvider::class)->shouldReceive('draft')->once()
        ->withArgs(fn (int $lookBack, array $commits): bool => $lookBack === $days && array_column($commits, 'sha') === $landed)
        ->andReturn(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => $draft])
        ->assertOk()
        ->assertExactJson(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);
})->with([
    'weekly' => ['weekly', 7, ['c3']],
    'monthly' => ['monthly', 30, ['c3', 'c1']],
]);

it('still drafts when nothing landed', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->with('dev-null-sh/dev-null.sh', 'production', Mockery::any(), Mockery::any())->andReturn([]);
    $this->mock(EmailDraftProvider::class)->shouldReceive('draft')->once()->with(7, [])->andReturn(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertOk()
        ->assertExactJson(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);
});

it('fails when the changelog can not be read', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->andThrow(new ConnectionException('timed out'));
    $this->mock(EmailDraftProvider::class)->shouldNotReceive('draft');

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertInternalServerError();
});

it('does not draft emails for non admins', function (): void {
    $this->mock(EmailDraftProvider::class)->shouldNotReceive('draft');

    $this->actingAs(User::factory()->create())
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertNotFound();
});
