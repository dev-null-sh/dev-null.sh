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
 * @param  list<array{number: int, title: string, merged_at: string|null}>  $pullRequests
 * @return list<array<string, mixed>>
 */
function emailDraftPullRequests(array $pullRequests): array
{
    return array_map(fn (array $pullRequest): array => [
        ...$pullRequest,
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/pull/{$pullRequest['number']}",
        'updated_at' => $pullRequest['merged_at'] ?? '2026-10-01T00:00:00Z',
        'merge_commit_sha' => mb_str_pad((string) $pullRequest['number'], 40, 'a'),
        'user' => ['id' => 1, 'login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
    ], $pullRequests);
}

it('drafts an email from what was merged in the time it looks back over', function (string $draft, int $days, array $merged): void {
    $this->mock(GitHubProvider::class)->shouldReceive('closedPullRequests')->with('dev-null-sh/dev-null.sh', 'production', Mockery::any(), Mockery::any())->andReturn(emailDraftPullRequests([
        ['number' => 3, 'title' => 'feat: email everyone', 'merged_at' => '2026-10-23T10:00:00Z'],
        ['number' => 2, 'title' => 'docs: closed without merging', 'merged_at' => null],
        ['number' => 1, 'title' => 'fix: older fix', 'merged_at' => '2026-10-10T10:00:00Z'],
    ]));
    $this->mock(EmailDraftProvider::class)->shouldReceive('draft')->once()
        ->withArgs(fn (int $lookBack, array $pullRequests): bool => $lookBack === $days && array_column($pullRequests, 'number') === $merged)
        ->andReturn(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => $draft])
        ->assertOk()
        ->assertExactJson(['subject' => 'What shipped', 'body' => "## New\n\n- Emails"]);
})->with([
    'weekly' => ['weekly', 7, [3]],
    'monthly' => ['monthly', 30, [3, 1]],
]);

it('still drafts when nothing was merged', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('closedPullRequests')->with('dev-null-sh/dev-null.sh', 'production', Mockery::any(), Mockery::any())->andReturn([]);
    $this->mock(EmailDraftProvider::class)->shouldReceive('draft')->once()->with(7, [])->andReturn(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('email-draft.store'), ['draft' => 'weekly'])
        ->assertOk()
        ->assertExactJson(['subject' => 'Quiet week', 'body' => 'Nothing shipped.']);
});

it('fails when the changelog can not be read', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('closedPullRequests')->andThrow(new ConnectionException('timed out'));
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
