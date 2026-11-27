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

it('opens the issue as the app, crediting the reporter, with the label of the command and the environment', function (string $command, string $label): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Search loses its filters', "Search loses its filters\n\nSteps: open search.\n\n<details>\n<summary>Environment</summary>\n\n| | |\n|---|---|\n| Os | `Linux` |\n| Viewport | `120x40` |\n\n</details>\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $this->user).').', [$label])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), [
        'command' => $command,
        'body' => "Search loses its filters\n\nSteps: open search.",
        'environment' => ['os' => 'Linux', 'viewport' => '120x40'],
    ])
        ->assertCreated()
        ->assertExactJson(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);
})->with([
    'feature' => ['/feature', 'enhancement'],
    'bug' => ['/bug', 'bug'],
]);

it('opens issues only for signed requests', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    $this->postJson(route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertUnauthorized();
});

it('validates the command, the body and the environment as the web does', function (array $payload, string $field): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown command' => [['command' => '/deploy', 'body' => 'Ship it.'], 'command'],
    'missing body' => [['command' => '/bug'], 'body'],
    'blank lines only' => [['command' => '/bug', 'body' => "a\n\n\n\n"], 'body'],
    'unknown environment field' => [['command' => '/bug', 'body' => 'Broken.', 'environment' => ['cookie' => 'secret']], 'environment'],
]);

it('opens no issue for a user in timeout', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');
});

it('keeps the draft with a code when GitHub rejects the issue or does not respond', function (Throwable $exception, string $code, string $message): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')->andThrow($exception);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertStatus(502)
        ->assertJsonPath('code', $code)
        ->assertJsonPath('message', $message);
})->with([
    'rejected' => [fn (): RequestException => new RequestException(new Response(new Psr7Response(422))), 'github_issue_refused', 'GitHub did not create the issue. Your draft is safe.'],
    'unavailable' => [fn (): ConnectionException => new ConnectionException('timeout'), 'github_issue_unanswered', 'GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.'],
]);

it('shares the limit of ten opened issues a day with the web, counting only opened ones', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('createIssue')->once()->andThrow(new ConnectionException('timeout'));
    $github->shouldReceive('createIssue')->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertStatus(502);
    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/deploy', 'body' => 'Nope.'])->assertUnprocessable();

    foreach (range(1, 5) as $attempt) {
        $this->travel(1)->minute();
        $this->actingAs($this->user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    foreach (range(6, 10) as $attempt) {
        $this->travel(1)->minute();
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertJsonPath('message', 'Issue limit reached: 10 a day. Try again tomorrow.')
        ->assertHeader('Retry-After', 86400 - 9 * 60);

    $this->actingAs($this->user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Issue limit reached: 10 a day. Try again tomorrow.']);

    $this->travel(1)->day();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken later.'])->assertCreated();
});

it('shares the web’s minute limit for issues', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->user)->postJson(route('issue.store'))->assertUnprocessable();
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'))->assertUnprocessable();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'))->assertTooManyRequests();
});
