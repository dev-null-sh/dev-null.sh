<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Models\UserTimeout;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

beforeEach(function (): void {
    $this->user = User::factory()->create(['handle' => 'taylor']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('opens the issue as the app, crediting the reporter, with the label of the command, as the web does', function (string $command, string $label): void {
    $body = "Search loses its filters\n\nSteps: select PHP, then navigate back.";

    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Search loses its filters', $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $this->user).').', [$label])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => $command, 'body' => $body])
        ->assertCreated()
        ->assertExactJson(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->assertDatabaseCount('commits', 0);
})->with([
    'feature' => ['/feature', 'enhancement'],
    'bug' => ['/bug', 'bug'],
]);

it('collapses blank lines and folds the environment the app sends into the body, as the web does', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Dark mode', "Dark mode\n\nplease\n\n<details>\n<summary>Environment</summary>\n\n| | |\n|---|---|\n| Browser | `devnull app` |\n| Os | `iOS 27.0` |\n\n</details>\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $this->user).').', ['enhancement'])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), [
        'command' => '/feature',
        'body' => "Dark mode\n\n\n\nplease",
        'environment' => ['browser' => 'devnull app', 'os' => 'iOS 27.0', 'language' => ''],
    ])->assertCreated();
});

it('keeps the draft when GitHub rejects the issue or does not respond, with a code', function (Throwable $exception, string $message): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')->andThrow($exception);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => $message, 'code' => 'github_issue_failed']);
})->with([
    'rejected' => [fn (): RequestException => new RequestException(new Response(new Psr7Response(422))), 'GitHub did not create the issue. Your draft is safe.'],
    'unavailable' => [fn (): ConnectionException => new ConnectionException('timeout'), 'GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.'],
]);

it('validates the command, the body and the environment as the web does', function (array $payload, string $field): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);
})->with([
    'unknown command' => [['command' => '/deploy', 'body' => 'Ship it.'], 'command'],
    'no command' => [['body' => 'Ship it.'], 'command'],
    'missing body' => [['command' => '/bug'], 'body'],
    'blank lines only' => [['command' => '/bug', 'body' => "a\n\n\n\n"], 'body'],
    'too short for an issue' => [['command' => '/bug', 'body' => 'ab'], 'body'],
    'too long' => [['command' => '/bug', 'body' => str_repeat('a', 4001)], 'body'],
    'unknown environment field' => [['command' => '/bug', 'body' => 'Broken.', 'environment' => ['cookie' => 'secret']], 'environment'],
]);

it('shares the web limit of ten opened issues a day, answering with a code', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')->andReturn([
        'number' => 123,
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    foreach (range(6, 10) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
        $this->travel(1)->minute();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 86400 - 5 * 60)
        ->assertExactJson(['message' => 'Issue limit reached: 10 a day. Try again tomorrow.', 'code' => 'rate_limited']);

    $this->travel(1)->day();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken later.'])->assertCreated();
});

it('does not count rejected drafts or failed attempts towards the limit', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('createIssue')->times(11)->andThrow(new RequestException(new Response(new Psr7Response(500))));
    $github->shouldReceive('createIssue')->once()->andReturn(['number' => 1, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/1']);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/deploy', 'body' => 'Nope.'])->assertUnprocessable();

    // More failures than the daily limit, a minute apart to stay under ten attempts a minute...
    foreach (range(1, 11) as $attempt) {
        $this->travel(1)->minute();
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertServiceUnavailable();
    }

    $this->travel(1)->minute();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertCreated();
});

it('may not open an issue while the user is in timeout', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');
});

it('rejects requests that are not signed', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    $this->actingAs($this->user)->postJson(route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertUnauthorized();
});
