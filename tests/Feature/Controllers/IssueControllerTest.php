<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;

it('opens the issue as the app, crediting the reporter, with the label of the command', function (string $command, string $label): void {
    $user = User::factory()->create(['handle' => 'taylor']);
    $body = "Search loses its filters\n\nSteps: select PHP, then navigate back.";

    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Search loses its filters', $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').', [$label])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => $command, 'body' => $body])
        ->assertCreated()
        ->assertExactJson(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->assertDatabaseCount('commits', 0);
})->with([
    'feature' => ['/feature', 'enhancement'],
    'bug' => ['/bug', 'bug'],
]);

it('uses the first top-level heading as the title and preserves the remaining markdown', function (string $draft, string $title, string $body): void {
    $user = User::factory()->create(['handle' => 'taylor']);

    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with($title, $body."\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').', ['bug'])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => $draft])
        ->assertCreated();
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
    $user = User::factory()->create(['handle' => 'taylor']);

    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Broken.', "Broken.\n\n<details>\n<summary>Environment</summary>\n\n| | |\n|---|---|\n| Browser | `Firefox 130.0` |\n| Viewport | `1440x900` |\n| Page | `/ x  y` |\n\n</details>\n\n---\n\nReported by @taylor via [/dev/null](".route('user.show', $user).').', ['bug'])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->actingAs($user)->postJson(route('issue.store'), [
        'command' => '/bug',
        'body' => 'Broken.',
        'environment' => ['browser' => 'Firefox 130.0', 'viewport' => '1440x900', 'page' => '/`x`|y', 'language' => ''],
    ])->assertCreated();
});

it('refuses environment fields it does not know', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    $this->actingAs(User::factory()->create())
        ->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.', 'environment' => ['cookie' => 'secret']])
        ->assertJsonValidationErrors('environment');
});

it('collapses blank lines and keeps the title within the limit of GitHub', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with(str_repeat('a', 256), Mockery::on(fn (string $body): bool => str_starts_with($body, str_repeat('a', 300)."\n\nplease\n\n---")), ['enhancement'])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('issue.store'), ['command' => '/feature', 'body' => str_repeat('a', 300)."\n\n\n\nplease"])
        ->assertCreated();
});

it('does not send images to GitHub', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')
        ->once()
        ->with('Broken.', Mockery::on(fn (string $body): bool => ! str_contains($body, '<img')), ['bug'])
        ->andReturn(['number' => 123, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123']);

    $this->actingAs(User::factory()->create())
        ->post(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.', 'images' => [UploadedFile::fake()->image('shot.png')]], ['Accept' => 'application/json'])
        ->assertCreated();
});

it('keeps the draft when GitHub rejects the issue or does not respond', function (Throwable $exception, string $message): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')->andThrow($exception);

    $this->actingAs(User::factory()->create())->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $text): bool => str_contains($text, $message));
})->with([
    'rejected' => [fn (): RequestException => new RequestException(new Response(new Psr7Response(422))), 'did not create the issue'],
    'unavailable' => [fn (): ConnectionException => new ConnectionException('timeout'), 'did not respond'],
]);

it('validates the command and the body', function (array $payload, string $field): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('createIssue');

    $this->actingAs(User::factory()->create())->postJson(route('issue.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown command' => [['command' => '/deploy', 'body' => 'Ship it.'], 'command'],
    'missing body' => [['command' => '/bug'], 'body'],
    'blank lines only' => [['command' => '/bug', 'body' => "a\n\n\n\n"], 'body'],
]);

it('requires authentication and limits opened issues to ten a day', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('createIssue')->andReturn([
        'number' => 123,
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/123',
    ]);

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
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('createIssue')->times(6)->andThrow(new RequestException(new Response(new Psr7Response(500))));
    $github->shouldReceive('createIssue')->once()->andReturn(['number' => 1, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/1']);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/deploy', 'body' => 'Nope.'])->assertUnprocessable();
    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertStatus(502);
        $this->travel(11)->seconds();
    }

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Broken.'])->assertCreated();
});
