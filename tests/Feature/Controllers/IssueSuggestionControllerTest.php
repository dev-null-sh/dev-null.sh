<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('uses the configured repository without reusing another repositorys cached match', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([
            'number' => 722, 'title' => 'Original', 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722',
        ]),
        'api.github.com/repos/example/upstream/issues/722' => Http::response([
            'number' => 722, 'title' => 'Different issue', 'html_url' => 'https://github.com/example/upstream/pull/722',
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Original');

    config(['services.github.repository' => 'example/upstream']);

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Different issue')
        ->assertJsonPath('issue.url', 'https://github.com/example/upstream/pull/722');
});

it('finds an exact issue or pull request without exposing its body', function (string $path): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([
            'number' => 722,
            'title' => 'Automatically link PRs and issues',
            'html_url' => "https://github.com/dev-null-sh/dev-null.sh/{$path}/722",
            'body' => 'Not needed for suggestions',
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    foreach (range(1, 2) as $attempt) {
        $this->getJson(route('issue-suggestion.index', ['q' => '722']))
            ->assertOk()
            ->assertExactJson(['issue' => [
                'number' => 722,
                'title' => 'Automatically link PRs and issues',
                'url' => "https://github.com/dev-null-sh/dev-null.sh/{$path}/722",
            ]]);
    }

    Http::assertSentCount(1);
})->with(['issues', 'pull']);

it('leaves missing or temporarily unavailable references alone', function (int $status): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([], $status),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
})->with([404, 403, 429, 500]);

it('asks GitHub only once for a reference it could not find', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([], 404),
    ]);

    $this->actingAs(User::factory()->create());

    foreach (range(1, 2) as $attempt) {
        $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertExactJson(['issue' => null]);
    }

    Http::assertSentCount(1);

    $this->travel(6)->minutes();

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertExactJson(['issue' => null]);

    Http::assertSentCount(2);
});

it('limits how many references each user may look up in a day', function (): void {
    Http::fake([
        'api.github.com/*' => Http::response([], 404),
    ]);

    $this->actingAs(User::factory()->create());

    foreach (range(1, 300) as $attempt) {
        if ($attempt % 30 === 0) {
            $this->travel(1)->minute();
        }

        $this->getJson(route('issue-suggestion.index', ['q' => (string) $attempt]))->assertOk();
    }

    $this->travel(1)->minute();

    $this->getJson(route('issue-suggestion.index', ['q' => '301']))->assertTooManyRequests();

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '301']))
        ->assertOk();
});

it('leaves references alone when GitHub cannot be reached', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::failedConnection(),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
});

it('requires authentication to look up references', function (): void {
    $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertUnauthorized();

    Http::assertNothingSent();
});

it('rejects invalid issue numbers before contacting GitHub', function (mixed $number): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => $number]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');

    Http::assertNothingSent();
})->with([null, '', '0', '-1', '0123', '123abc', '1.5', '12345678901', [['722']]]);

it('expires cached issue titles', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::sequence()
            ->push(['number' => 722, 'title' => 'Original title', 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722'])
            ->push(['number' => 722, 'title' => 'Updated title', 'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722']),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Original title');

    $this->travel(6)->minutes();

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Updated title');
});
