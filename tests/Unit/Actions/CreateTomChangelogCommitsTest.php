<?php

declare(strict_types=1);

use App\Actions\CreateTomChangelogCommits;
use App\Actions\CreateTomFriendship;
use App\Ai\Agents\ChangelogWriter;
use App\Contracts\Services\GitHubProvider;
use App\Jobs\AnnounceProductionCommits;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

function productionSha(int $number): string
{
    return mb_str_pad((string) $number, 40, '0', STR_PAD_LEFT);
}

/**
 * @param  list<int>  $parents
 * @return array{sha: string, message: string, url: string, committed_at: string, parents: list<string>, author: array{id: int|null, login: string, avatar_url: string}}
 */
function productionCommit(int $number, string $message, array $parents, ?int $githubId = null, string $login = 'someone'): array
{
    return [
        'sha' => productionSha($number),
        'message' => $message,
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/commit/'.productionSha($number),
        'committed_at' => '2026-11-11T12:00:00Z',
        'parents' => array_map(productionSha(...), $parents),
        'author' => ['id' => $githubId, 'login' => $login, 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
    ];
}

beforeEach(function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    Queue::fake([ModerateCommit::class, EmbedCommit::class]);
    $this->tom = User::factory()->create(['handle' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
});

it('only remembers where production is on its first run, so old changes are never announced', function (): void {
    ChangelogWriter::fake()->preventStrayPrompts();
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->with('dev-null-sh/dev-null.sh', 'production', 1, 100)->once()
        ->andReturn([productionCommit(2, 'feat: two (#2)', [1]), productionCommit(1, 'feat: one (#1)', [])]);

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(2))
        ->and(Commit::query()->count())->toBe(0);
    ChangelogWriter::assertNeverPrompted();
});

it('posts one commit as Tom for every commit on production since the last one, oldest first', function (): void {
    $member = User::factory()->create(['handle' => 'xheaven', 'github_id' => '14284867']);
    ChangelogWriter::fake(['Fixed the dot. *whirr*', 'You can pick emoji while quoting now :-)', 'Behind the scenes, the tests run faster.'])->preventStrayPrompts();
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->once()->andReturn([
        productionCommit(5, 'fix: centre the dot (#1544)', [4], login: 'nunomaduro'),
        productionCommit(4, "Merge pull request #1468 from xheaven/emoji\n\nfeat: add emoji picker to quote composer", [3, 9], 14284867, 'xHeaven'),
        productionCommit(9, 'wip: a commit of the merged branch', [3]),
        productionCommit(3, "Merge branch 'production' into production", [2, 8]),
        productionCommit(2, 'test: run the suite in parallel', [1], login: 'ahosker'),
        productionCommit(1, 'feat: announced before (#1)', []),
    ]);
    $github->shouldReceive('pullRequestDescription')->with('dev-null-sh/dev-null.sh', 1468)->once()->andReturn('Adds the picker. Trello: https://trello.com/c/abc');
    $github->shouldReceive('pullRequestDescription')->with('dev-null-sh/dev-null.sh', 1544)->once()->andReturnNull();
    Cache::forever(CreateTomChangelogCommits::LastAnnouncedKey, productionSha(1));

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Commit::query()->oldest('id')->get()->map(fn (Commit $commit): array => [$commit->user_id, $commit->parent_id, $commit->body])->all())->toBe([
        [$this->tom->id, null, "Fixed the dot. *whirr*\n\nBy ahosker: https://github.com/dev-null-sh/dev-null.sh/commit/".productionSha(2)],
        [$this->tom->id, null, "You can pick emoji while quoting now :-)\n\nBy {$member->mention()}: https://github.com/dev-null-sh/dev-null.sh/pull/1468"],
        [$this->tom->id, null, "Behind the scenes, the tests run faster.\n\nBy nunomaduro: https://github.com/dev-null-sh/dev-null.sh/pull/1544"],
    ])->and(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(5));
    ChangelogWriter::assertPrompted('{"type":"test","breaking":false,"title":"Run the suite in parallel","description":null}');
    ChangelogWriter::assertPrompted('{"type":"feat","breaking":false,"title":"Add emoji picker to quote composer","description":"Adds the picker. Trello: https://trello.com/c/abc"}');
    ChangelogWriter::assertPrompted('{"type":"fix","breaking":false,"title":"Centre the dot","description":null}');
});

it('posts nothing when production has not moved', function (): void {
    ChangelogWriter::fake()->preventStrayPrompts();
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->once()->andReturn([productionCommit(1, 'feat: one (#1)', [])]);
    Cache::forever(CreateTomChangelogCommits::LastAnnouncedKey, productionSha(1));

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Commit::query()->count())->toBe(0);
    ChangelogWriter::assertNeverPrompted();
});

it('starts again from the latest commit when it cannot find where it left off', function (): void {
    ChangelogWriter::fake()->preventStrayPrompts();
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->once()->andReturn([productionCommit(2, 'feat: two (#2)', [1]), productionCommit(1, 'feat: one (#1)', [])]);
    Cache::forever(CreateTomChangelogCommits::LastAnnouncedKey, productionSha(77));

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(2))
        ->and(Commit::query()->count())->toBe(0);
    ChangelogWriter::assertNeverPrompted();
});

it('picks up from the commit it failed on', function (): void {
    ChangelogWriter::fake(['One.', 'Two.', 'Three.']);
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('commits')->twice()->andReturn([
        productionCommit(3, 'feat: three (#3)', [2]),
        productionCommit(2, 'feat: two (#2)', [1]),
        productionCommit(1, 'feat: one (#1)', []),
    ]);
    $github->shouldReceive('pullRequestDescription')->with('dev-null-sh/dev-null.sh', 2)->once()->andReturnNull();
    $github->shouldReceive('pullRequestDescription')->with('dev-null-sh/dev-null.sh', 3)->once()->andThrow(new ConnectionException('GitHub is down'));
    $github->shouldReceive('pullRequestDescription')->with('dev-null-sh/dev-null.sh', 3)->once()->andReturnNull();
    Cache::forever(CreateTomChangelogCommits::LastAnnouncedKey, productionSha(1));

    expect(fn () => resolve(CreateTomChangelogCommits::class)->handle())->toThrow(ConnectionException::class)
        ->and(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(2));

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Commit::query()->oldest('id')->pluck('body')->map(fn (string $body): string => strtok($body, "\n"))->all())->toBe(['One.', 'Two.'])
        ->and(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(3));
});

it('does nothing without Tom', function (): void {
    $this->tom->delete();
    $this->mock(GitHubProvider::class)->shouldNotReceive('commits');

    resolve(CreateTomChangelogCommits::class)->handle();

    expect(Cache::has(CreateTomChangelogCommits::LastAnnouncedKey))->toBeFalse();
});

it('runs from the job', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('commits')->once()->andReturn([productionCommit(1, 'feat: one (#1)', [])]);

    dispatch_sync(new AnnounceProductionCommits());

    expect(Cache::get(CreateTomChangelogCommits::LastAnnouncedKey))->toBe(productionSha(1));
});
