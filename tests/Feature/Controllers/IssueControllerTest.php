<?php

declare(strict_types=1);

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key !== false);
    openssl_pkey_export($key, $privateKey);
    $this->publicKey = openssl_pkey_get_details($key)['key'];

    config(['services.github_app' => ['id' => '12345', 'private_key' => str_replace("\n", '\n', $privateKey)]]);
});

it('files issues in the configured upstream with a token scoped to that repository', function (): void {
    config(['services.github.repository' => 'example/upstream']);
    Http::fake([
        'api.github.com/repos/example/upstream/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/example/upstream/issues' => Http::response([
            'number' => 123, 'html_url' => 'https://github.com/example/upstream/issues/123',
        ], 201),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken search'])
        ->assertCreated()
        ->assertJsonPath('url', 'https://github.com/example/upstream/issues/123');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/access_tokens')
        && $request['repositories'] === ['upstream']
        && $request['permissions'] === ['issues' => 'write']);
});

function fakeGitHubApp(array $issue = []): void
{
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues' => Http::response([
            'number' => 123,
            'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123',
            ...$issue,
        ], 201),
    ]);
}

it('opens the issue as the app, crediting the reporter, with the label of the command', function (string $command, string $label): void {
    fakeGitHubApp();
    $user = User::factory()->create(['username' => 'taylor']);
    $body = "Search loses its filters\n\nSteps: select PHP, then navigate back.";

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => $command, 'body' => $body])
        ->assertCreated()
        ->assertExactJson(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/dev-null-sh/dev-null.sh/issues'
        && $request->hasHeader('Authorization', 'Bearer installation-token')
        && $request['title'] === 'Search loses its filters'
        && $request['body'] === $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').'
        && $request['labels'] === [$label]);
    $this->assertDatabaseCount('commits', 0);
})->with([
    'feature' => ['/feature', 'enhancement'],
    'bug' => ['/bug', 'bug'],
]);

it('uses the first top-level heading as the title and preserves the remaining markdown', function (string $draft, string $title, string $body): void {
    fakeGitHubApp();
    $user = User::factory()->create(['username' => 'taylor']);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => $draft])
        ->assertCreated();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues')
        && $request['title'] === $title
        && $request['body'] === $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').');
})->with([
    'template' => [
        "# Search loses **filters**\n\n## Steps\n1. Select PHP.\n\n## Expected vs. actual\n- [ ] Keep `PHP` selected.\n\n| Expected | Actual |\n| --- | --- |\n| PHP | All |\n\n# More context\nSee ~~old~~ [search](/search).",
        'Search loses filters',
        "## Steps\n1. Select PHP.\n\n## Expected vs. actual\n- [ ] Keep `PHP` selected.\n\n| Expected | Actual |\n| --- | --- |\n| PHP | All |\n\n# More context\nSee ~~old~~ [search](/search).",
    ],
    'not a heading inside code or a quote' => [
        "```sh\n# A comment\n```\n\n> # Quoted heading\n\n## Context\nBefore the title.\n\n# Actual title\n\nAfter the title.",
        'Actual title',
        "```sh\n# A comment\n```\n\n> # Quoted heading\n\n## Context\nBefore the title.\n\n\nAfter the title.",
    ],
    'heading only' => ['# A short report', 'A short report', ''],
    'long heading' => ['# '.str_repeat('a', 300)."\n\nDetails.", str_repeat('a', 256), 'Details.'],
]);

it('folds the environment of the reporter into the body, ahead of the credit', function (): void {
    fakeGitHubApp();
    $user = User::factory()->create(['username' => 'taylor']);

    $this->actingAs($user)->postJson(route('issue.store'), [
        'command' => '/bug',
        'body' => 'Broken.',
        'environment' => ['browser' => 'Firefox 130.0', 'viewport' => '1440x900', 'page' => '/`x`|y', 'language' => ''],
    ])->assertCreated();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues')
        && $request['body'] === "Broken.\n\n<details>\n<summary>Environment</summary>\n\n| | |\n|---|---|\n| Browser | `Firefox 130.0` |\n| Viewport | `1440x900` |\n| Page | `/ x  y` |\n\n</details>\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').');
});

it('refuses environment fields it does not know', function (): void {
    fakeGitHubApp();

    $this->actingAs(User::factory()->create())
        ->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.', 'environment' => ['cookie' => 'secret']])
        ->assertJsonValidationErrors('environment');
});

it('asks for an installation token limited to writing issues on this repository, signed by the app', function (): void {
    fakeGitHubApp();

    $this->actingAs(User::factory()->create())->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertCreated();

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://api.github.com/app/installations/99/access_tokens') {
            return false;
        }

        $jwt = JWT::decode(mb_substr($request->header('Authorization')[0], 7), new Key($this->publicKey, 'RS256'));

        return $jwt->iss === '12345'
            && $request['repositories'] === ['dev-null.sh']
            && $request['permissions'] === ['issues' => 'write'];
    });
});

it('reuses the installation token across issues', function (): void {
    fakeGitHubApp();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'First.'])->assertCreated();
    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Second.'])->assertCreated();

    Http::assertSentCount(4);
});

it('collapses blank lines and keeps the title within the limit of GitHub', function (): void {
    fakeGitHubApp();

    $this->actingAs(User::factory()->create())
        ->postJson(route('issue.store'), ['command' => '/feature', 'body' => str_repeat('a', 300)."\n\n\n\nplease"])
        ->assertCreated();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues')
        && $request['title'] === str_repeat('a', 256)
        && str_starts_with((string) $request['body'], str_repeat('a', 300)."\n\nplease\n\n---"));
});

it('does not send images to GitHub', function (): void {
    fakeGitHubApp();

    $this->actingAs(User::factory()->create())
        ->post(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.', 'images' => [UploadedFile::fake()->image('shot.png')]], ['Accept' => 'application/json'])
        ->assertCreated();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issues')
        && ! str_contains((string) $request['body'], '<img'));
});

it('keeps the draft when GitHub rejects the issue or does not respond', function (Closure $response, string $message): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues' => $response,
    ]);

    $this->actingAs(User::factory()->create())->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $text): bool => str_contains($text, $message));
})->with([
    'rejected' => [fn () => Http::response(['message' => 'Validation Failed'], 422), 'did not create the issue'],
    'unavailable' => [fn () => throw new ConnectionException('timeout'), 'did not respond'],
]);

it('validates the command and the body', function (array $payload, string $field): void {
    Http::fake();

    $this->actingAs(User::factory()->create())->postJson(route('issue.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Http::assertNothingSent();
})->with([
    'unknown command' => [['command' => '/deploy', 'body' => 'Ship it.'], 'command'],
    'missing body' => [['command' => '/bug'], 'body'],
    'blank lines only' => [['command' => '/bug', 'body' => "a\n\n\n\n"], 'body'],
]);

it('requires authentication and limits opened issues to ten a day', function (): void {
    fakeGitHubApp();
    $this->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertUnauthorized();

    $user = User::factory()->create();
    foreach (range(1, 10) as $attempt) {
        $this->travel(1)->minute();
        $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => "Broken {$attempt}."])->assertCreated();
    }

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Issue limit reached: 10 a day. Try again tomorrow.');

    $this->travel(23)->hours();
    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken again.'])->assertTooManyRequests();

    $this->travel(1)->hours();
    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken later.'])->assertCreated();
});

it('does not count rejected drafts or failed attempts towards the limit', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues' => Http::sequence()
            ->push([], 500)->push([], 500)->push([], 500)
            ->push([], 500)->push([], 500)->push([], 500)
            ->push(['number' => 1, 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/1'], 201),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/deploy', 'body' => 'Nope.'])->assertUnprocessable();
    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertStatus(502);
        $this->travel(11)->seconds();
    }

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertCreated();
});
