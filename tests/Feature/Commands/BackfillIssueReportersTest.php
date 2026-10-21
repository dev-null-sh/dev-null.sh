<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->path = Storage::fake('local')->path('reporters.json');
});

it('backfills a reviewed immutable reporter identity even when their username was reclaimed', function (): void {
    $reporter = User::factory()->create(['github_id' => '42', 'username' => 'renamed-reporter']);
    $impostor = User::factory()->create(['github_id' => '43', 'username' => 'original-reporter']);
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"42"}}');
    Http::fake(['api.github.com/repos/nunomaduro/dev-null.sh/issues/894' => Http::response(legacyReporterIssue())]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Awarded the contributor badge to 1 users.')
        ->assertSuccessful();

    expect($reporter->refresh()->is_contributor)->toBeTrue()
        ->and($impostor->refresh()->is_contributor)->toBeFalse();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/nunomaduro/dev-null.sh/issues/894');

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Awarded the contributor badge to 0 users.')
        ->assertSuccessful();

    expect($impostor->refresh()->is_contributor)->toBeFalse();
});

it('retains existing awards and does not award accounts absent from the reviewed mapping', function (): void {
    $reporter = User::factory()->create(['github_id' => '42', 'is_contributor' => true]);
    $other = User::factory()->create(['github_id' => '43']);
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"42"}}');
    Http::fake(['*' => Http::response(legacyReporterIssue())]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Awarded the contributor badge to 0 users.')
        ->assertSuccessful();

    expect($reporter->refresh()->is_contributor)->toBeTrue()
        ->and($other->refresh()->is_contributor)->toBeFalse();
});

it('accepts an empty reviewed mapping without contacting github', function (): void {
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{}}');

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Awarded the contributor badge to 0 users.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('does not create a local user for a reviewed reporter who is not registered', function (): void {
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"42"}}');
    Http::fake(['*' => Http::response(legacyReporterIssue())]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Awarded the contributor badge to 0 users.')
        ->assertSuccessful();

    $this->assertDatabaseCount('users', 0);
});

it('rejects an invalid mapping before contacting github or awarding any users', function (string $contents): void {
    $user = User::factory()->create(['github_id' => '42']);
    Storage::disk('local')->put('reporters.json', $contents);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Invalid reporter mapping:')
        ->assertFailed();

    expect($user->refresh()->is_contributor)->toBeFalse();
    Http::assertNothingSent();
})->with([
    'blank file' => '',
    'invalid json' => '{',
    'nonobject document' => '[]',
    'missing repository' => '{"reporters":{"894":"42"}}',
    'wrong repository' => '{"repository":"other/repo","reporters":{"894":"42"}}',
    'url repository' => '{"repository":"https://evil.test/repo","reporters":{"894":"42"}}',
    'missing reporters' => '{"repository":"nunomaduro/dev-null.sh"}',
    'list instead of map' => '{"repository":"nunomaduro/dev-null.sh","reporters":[]}',
    'zero issue' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"0":"42"}}',
    'negative issue' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"-1":"42"}}',
    'leading zero issue' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"0894":"42"}}',
    'issue url' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"https://evil.test":"42"}}',
    'issue path traversal' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"../user":"42"}}',
    'numeric reporter value' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":42}}',
    'zero reporter' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"0"}}',
    'negative reporter' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"-42"}}',
    'leading zero reporter' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"042"}}',
    'non-github reporter' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"local:reporter"}}',
    'invalid after valid entry' => '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"42","895":43}}',
]);

it('requires an existing local mapping file', function (string $path): void {
    $this->artisan('contributors:backfill-reporters', ['path' => $path])
        ->expectsOutputToContain('Invalid reporter mapping:')
        ->assertFailed();

    Http::assertNothingSent();
})->with(['missing-reporters.json', 'https://evil.test/reporters.json', 'php://memory']);

it('verifies every reviewed issue before awarding any users', function (array $issue): void {
    $user = User::factory()->create(['github_id' => '42']);
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"893":"42","894":"43"}}');
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/893' => Http::response(legacyReporterIssue(['number' => 893])),
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/894' => Http::response(legacyReporterIssue($issue)),
    ]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Invalid reporter mapping:')
        ->assertFailed();

    expect($user->refresh()->is_contributor)->toBeFalse();
})->with([
    'other author' => [['user' => ['id' => 17]]],
    'same bot name different id' => [['user' => ['id' => 17, 'login' => 'dev-null-bot[bot]']]],
    'missing author' => [['user' => null]],
    'wrong issue number' => [['number' => 895]],
    'pull request' => [['pull_request' => ['url' => 'https://api.github.com/repos/nunomaduro/dev-null.sh/pulls/894']]],
    'missing footer' => [['body' => 'Reported by @original-reporter']],
    'untrusted footer hostname' => [['body' => "Report\n\n---\n\nReported by @original-reporter via [/dev/null](https://evil.test/@original-reporter)."]],
    'mismatched footer usernames' => [['body' => fn (): string => "Report\n\n---\n\nReported by @original-reporter via [/dev/null](".url('/@other-reporter').').']],
    'footer followed by other text' => [['body' => fn (): string => legacyReporterIssue()['body']."\nMore text"]],
    'null body' => [['body' => null]],
]);

it('uses the configured immutable github app bot id', function (): void {
    config(['services.github_app.bot_id' => '123']);
    $user = User::factory()->create(['github_id' => '42']);
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"894":"42"}}');
    Http::fake(['*' => Http::response(legacyReporterIssue(['user' => ['id' => 123]]))]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])->assertSuccessful();

    expect($user->refresh()->is_contributor)->toBeTrue();
});

it('reports provider failures without partially awarding the reviewed mapping', function (Closure $response, string $exception): void {
    Exceptions::fake();
    $user = User::factory()->create(['github_id' => '42']);
    Storage::disk('local')->put('reporters.json', '{"repository":"nunomaduro/dev-null.sh","reporters":{"893":"42","894":"43"}}');
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/893' => Http::response(legacyReporterIssue(['number' => 893])),
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/894' => $response,
    ]);

    $this->artisan('contributors:backfill-reporters', ['path' => $this->path])
        ->expectsOutputToContain('Could not verify historical reports with GitHub. Try again later.')
        ->assertFailed();

    expect($user->refresh()->is_contributor)->toBeFalse();
    Exceptions::assertReported($exception);
})->with([
    'rate limit' => [fn () => Http::response([], 429), RequestException::class],
    'connection' => [fn () => throw new ConnectionException('timeout'), ConnectionException::class],
]);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyReporterIssue(array $overrides = []): array
{
    $issue = [
        'number' => 894,
        'user' => ['id' => 344368677],
        'body' => "Report\n\n---\n\nReported by @original-reporter via [/dev/null](".url('/@original-reporter').').',
        ...$overrides,
    ];

    if ($issue['body'] instanceof Closure) {
        $issue['body'] = $issue['body']();
    }

    return $issue;
}
