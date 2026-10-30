<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('finds the issue or pull request with that number, as the web composer does', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->with('dev-null-sh/dev-null.sh', 722)->andReturn([
        'number' => 722,
        'title' => 'Automatically link PRs and issues',
        'url' => 'https://github.com/dev-null-sh/dev-null.sh/pull/722',
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
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->with('dev-null-sh/dev-null.sh', 722)->andReturnNull();

    signedApiRequest($this->keyPair, 'GET', route('api-issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
});

it('rejects invalid issue numbers before contacting GitHub', function (string $number): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('issue');

    signedApiRequest($this->keyPair, 'GET', route('api-issue-suggestion.index', ['q' => $number]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with(['', '0', '-1', '0123', '123abc', '12345678901']);

it('requires a signed request', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('issue');

    $this->getJson(route('api-issue-suggestion.index', ['q' => '722']))->assertUnauthorized();
});
