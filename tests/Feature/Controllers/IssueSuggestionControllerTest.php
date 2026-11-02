<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

it('uses the configured repository without reusing another repositorys cached match', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('issue')->with('dev-null-sh/dev-null.sh', 722)->andReturn([
        'number' => 722, 'title' => 'Original', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722',
    ]);
    $github->shouldReceive('issue')->with('example/upstream', 722)->andReturn([
        'number' => 722, 'title' => 'Different issue', 'url' => 'https://github.com/example/upstream/pull/722',
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Original');

    config(['services.github.repository' => 'example/upstream']);

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Different issue')
        ->assertJsonPath('issue.url', 'https://github.com/example/upstream/pull/722');
});

it('finds an exact issue or pull request', function (string $path): void {
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->with('dev-null-sh/dev-null.sh', 722)->once()->andReturn([
        'number' => 722,
        'title' => 'Automatically link PRs and issues',
        'url' => "https://github.com/dev-null-sh/dev-null.sh/{$path}/722",
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
})->with(['issues', 'pull']);

it('leaves temporarily unavailable references alone', function (int $status): void {
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->andThrow(new RequestException(new Response(new Psr7Response($status))));

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
})->with([403, 429, 500]);

it('asks GitHub only once for a reference it could not find', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('issue')->with('dev-null-sh/dev-null.sh', 722)->andReturnNull();

    $this->actingAs(User::factory()->create());

    foreach (range(1, 2) as $attempt) {
        $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertExactJson(['issue' => null]);
    }

    $github->shouldHaveReceived('issue')->once();

    $this->travel(6)->minutes();

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertExactJson(['issue' => null]);

    $github->shouldHaveReceived('issue')->twice();
});

it('limits how many references each user may look up in a day', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->andReturnNull();

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
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->andThrow(new ConnectionException('timed out'));

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertOk()
        ->assertExactJson(['issue' => null]);
});

it('requires authentication to look up references', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('issue');

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))->assertUnauthorized();
});

it('rejects invalid issue numbers before contacting GitHub', function (mixed $number): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('issue');

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => $number]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with([null, '', '0', '-1', '0123', '123abc', '1.5', '12345678901', [['722']]]);

it('expires cached issue titles', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('issue')->andReturn(
        ['number' => 722, 'title' => 'Original title', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722'],
        ['number' => 722, 'title' => 'Updated title', 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues/722'],
    );

    $this->actingAs(User::factory()->create())
        ->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Original title');

    $this->travel(6)->minutes();

    $this->getJson(route('issue-suggestion.index', ['q' => '722']))
        ->assertJsonPath('issue.title', 'Updated title');
});
