<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key !== false);
    openssl_pkey_export($key, $privateKey);

    config(['services.github_app' => ['id' => '12345', 'private_key' => str_replace("\n", '\n', $privateKey)]]);

    $this->user = User::factory()->create(['username' => 'taylor']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('opens the issue as the app, crediting the reporter, with the label of the command, as the web does', function (string $command, string $label): void {
    fakeGitHubApp();
    $body = "Search loses its filters\n\nSteps: select PHP, then navigate back.";

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => $command, 'body' => $body])
        ->assertCreated()
        ->assertExactJson(['number' => 123, 'url' => 'https://github.com/nunomaduro/dev-null.sh/issues/123']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/nunomaduro/dev-null.sh/issues'
        && $request->hasHeader('Authorization', 'Bearer installation-token')
        && $request['title'] === 'Search loses its filters'
        && $request['body'] === $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $this->user).').'
        && $request['labels'] === [$label]);
    $this->assertDatabaseCount('commits', 0);
})->with([
    'feature' => ['/feature', 'enhancement'],
    'bug' => ['/bug', 'bug'],
]);

it('collapses blank lines before measuring and sending the body', function (): void {
    fakeGitHubApp();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/feature', 'body' => "Dark mode\n\n\n\nplease"])
        ->assertCreated();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues')
        && str_starts_with((string) $request['body'], "Dark mode\n\nplease\n\n---"));
});

it('keeps the draft when GitHub rejects the issue or does not respond, with a code', function (Closure $response, string $message): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/nunomaduro/dev-null.sh/issues' => $response,
    ]);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => $message, 'code' => 'github_issue_failed']);
})->with([
    'rejected' => [fn () => Http::response(['message' => 'Validation Failed'], 422), 'GitHub did not create the issue. Your draft is safe.'],
    'unavailable' => [fn () => throw new ConnectionException('timeout'), 'GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.'],
]);

it('validates the command and the body as the web does', function (array $payload, string $field): void {
    Http::fake();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);

    Http::assertNothingSent();
})->with([
    'unknown command' => [['command' => '/deploy', 'body' => 'Ship it.'], 'command'],
    'no command' => [['body' => 'Ship it.'], 'command'],
    'missing body' => [['command' => '/bug'], 'body'],
    'blank lines only' => [['command' => '/bug', 'body' => "a\n\n\n\n"], 'body'],
    'too long' => [['command' => '/bug', 'body' => str_repeat('a', 4001)], 'body'],
]);

it('shares the web limit of five opened issues an hour, answering with a code', function (): void {
    fakeGitHubApp();

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($this->user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    foreach (range(4, 5) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 3600)
        ->assertExactJson(['message' => 'Issue limit reached. Try again in an hour.', 'code' => 'rate_limited']);

    $this->travel(1)->hours();

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken later.'])->assertCreated();
});

it('does not count rejected drafts or failed attempts towards the limit', function (): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/nunomaduro/dev-null.sh/issues' => Http::sequence()
            ->push([], 500)->push([], 500)->push([], 500)
            ->push([], 500)->push([], 500)->push([], 500)
            ->push(['number' => 1, 'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/1'], 201),
    ]);

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/deploy', 'body' => 'Nope.'])->assertUnprocessable();

    foreach (range(1, 6) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertStatus(502);
        $this->travel(11)->seconds();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertCreated();
});

it('rejects requests that are not signed', function (): void {
    Http::fake();

    $this->actingAs($this->user)->postJson(route('api-issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertUnauthorized();

    Http::assertNothingSent();
});
