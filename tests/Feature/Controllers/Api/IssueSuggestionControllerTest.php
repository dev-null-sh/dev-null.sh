<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('finds the issue or pull request with that number, as the web composer does', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([
            'number' => 722,
            'title' => 'Automatically link PRs and issues',
            'html_url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/722',
            'body' => 'Not needed for suggestions',
        ]),
    ]);

    signedApiRequest($this->keyPair, 'GET', route('api-issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => [
            'number' => 722,
            'title' => 'Automatically link PRs and issues',
            'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/722',
        ]]);
});

it('answers null for an issue GitHub does not have', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/722' => Http::response([], 404),
    ]);

    signedApiRequest($this->keyPair, 'GET', route('api-issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
});

it('rejects invalid issue numbers before contacting GitHub', function (string $number): void {
    Http::fake();

    signedApiRequest($this->keyPair, 'GET', route('api-issue-suggestion.index', ['q' => $number]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');

    Http::assertNothingSent();
})->with(['', '0', '-1', '0123', '123abc', '12345678901']);

it('requires a signed request', function (): void {
    Http::fake();

    $this->getJson(route('api-issue-suggestion.index', ['q' => '722']))->assertUnauthorized();

    Http::assertNothingSent();
});
