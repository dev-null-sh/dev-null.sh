<?php

declare(strict_types=1);

use App\Queries\DependencyContributorsQuery;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A package's metadata the way Packagist serves it, newest version first, cut down to what the query reads.
 *
 * @return array<string, mixed>
 */
function packagistMetadata(string $package, string $url): array
{
    return ['packages' => [$package => [['name' => $package, 'source' => ['type' => 'git', 'url' => $url]]]]];
}

/**
 * A repository's contributors the way GitHub lists them, people and bots alike.
 *
 * @param  list<int>  $users
 * @param  list<int>  $bots
 * @return list<array{id: int, type: string}>
 */
function githubContributors(array $users, array $bots = []): array
{
    return [
        ...array_map(fn (int $id): array => ['id' => $id, 'type' => 'User'], $users),
        ...array_map(fn (int $id): array => ['id' => $id, 'type' => 'Bot'], $bots),
    ];
}

it("finds the people behind the packages a user's repositories depend on", function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([
            ['full_name' => 'jess/blog', 'fork' => false],
            ['full_name' => 'jess/framework', 'fork' => true],
        ]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response([
            'require' => ['php' => '^8.5', 'ext-mbstring' => '*', 'laravel/framework' => '^13.0'],
            'require-dev' => ['pestphp/pest' => '^5.0'],
        ]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response([
            'dependencies' => ['vite' => '^8.0', '@inertiajs/core' => '^3.0'],
            'devDependencies' => ['@inertiajs/react' => '^3.0', '@types/node' => '^24.0'],
        ]),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'repo.packagist.org/p2/pestphp/pest.json' => Http::response(packagistMetadata('pestphp/pest', 'https://github.com/pestphp/pest.git')),
        'registry.npmjs.org/vite/latest' => Http::response(['repository' => ['type' => 'git', 'url' => 'git+https://github.com/vitejs/vite.git#main']]),
        'registry.npmjs.org/@inertiajs*' => Http::response(['repository' => 'github:inertiajs/inertia']),
        'api.github.com/repos/laravel/framework/contributors*' => Http::response(githubContributors([1], bots: [2])),
        'api.github.com/repos/pestphp/pest/contributors*' => Http::response(githubContributors([3, 1])),
        'api.github.com/repos/vitejs/vite/contributors*' => Http::response(githubContributors([4])),
        'api.github.com/repos/inertiajs/inertia/contributors*' => Http::response(githubContributors([5])),
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe(['1', '3', '4', '5']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/jess/repos?type=owner&sort=pushed&per_page=10');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/laravel/framework/contributors?per_page=5');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://repo.packagist.org') && $request->hasHeader('User-Agent', 'devnull/1.0 (+'.config('app.url').')'));

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'inertiajs/inertia/contributors')))->toHaveCount(1);
});

it('looks up the twenty packages the most repositories depend on, counting each repository once', function (): void {
    $packages = array_map(fn (int $number): string => "acme/package-{$number}", range(1, 22));

    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([
            ['full_name' => 'jess/one', 'fork' => false],
            ['full_name' => 'jess/two', 'fork' => false],
        ]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/one/HEAD/composer.json' => Http::response([
            'require' => array_fill_keys($packages, '*'),
            'require-dev' => ['acme/package-21' => '*'],
        ]),
        'raw.githubusercontent.com/jess/two/HEAD/composer.json' => Http::response(['require' => ['acme/package-22' => '*']]),
        'raw.githubusercontent.com/*/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/*' => Http::response('', 404),
    ]);

    resolve(DependencyContributorsQuery::class)->get('jess');

    $lookedUp = Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://repo.packagist.org'))
        ->map(fn (array $exchange): string => $exchange[0]->url());

    expect($lookedUp)->toHaveCount(20)
        ->and($lookedUp->first())->toBe('https://repo.packagist.org/p2/acme/package-22.json')
        ->and($lookedUp->last())->toBe('https://repo.packagist.org/p2/acme/package-19.json');
});

it('fills in with the repositories the user starred most recently, other than their own', function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([
            ['full_name' => 'tldraw/tldraw', 'owner' => ['login' => 'tldraw']],
            ['full_name' => 'Jess/dotfiles', 'owner' => ['login' => 'Jess']],
            ['full_name' => 'laravel/framework', 'owner' => ['login' => 'laravel']],
        ]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'api.github.com/repos/laravel/framework/contributors*' => Http::response(githubContributors([1])),
        'api.github.com/repos/tldraw/tldraw/contributors*' => Http::response(githubContributors([2])),
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe(['1', '2']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/users/jess/starred?per_page=20');

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'laravel/framework/contributors')))->toHaveCount(1);
});

it('asks about twenty repositories at most, each once', function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([
            ['full_name' => 'laravel/framework', 'owner' => ['login' => 'laravel']],
            ...array_map(
                fn (int $number): array => ['full_name' => "acme/repository-{$number}", 'owner' => ['login' => 'acme']],
                range(1, 24),
            ),
        ]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'api.github.com/repos/*' => Http::response([]),
    ]);

    resolve(DependencyContributorsQuery::class)->get('jess');

    $askedAbout = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/contributors'))
        ->map(fn (array $exchange): string => Str::between($exchange[0]->url(), '/repos/', '/contributors'));

    expect($askedAbout)->toHaveCount(20)
        ->and($askedAbout->first())->toBe('laravel/framework')
        ->and($askedAbout->last())->toBe('acme/repository-19');
});

it('reads the stars only while the packages leave room among the repositories to ask about', function (bool $shared, int $stars): void {
    $packages = array_map(fn (int $number): string => "acme/package-{$number}", range(1, 20));

    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => array_fill_keys($packages, '*')]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/*' => function (Request $request) use ($shared): PromiseInterface {
            $package = Str::between($request->url(), '/p2/', '.json');
            $repository = $shared && $package === 'acme/package-20' ? 'acme/package-19' : $package;

            return Http::response(packagistMetadata($package, "https://github.com/{$repository}"));
        },
        'api.github.com/repos/*' => Http::response([]),
    ]);

    resolve(DependencyContributorsQuery::class)->get('jess');

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/starred')))->toHaveCount($stars);
})->with([
    'twenty repositories' => [false, 0],
    'nineteen, as two packages share one' => [true, 1],
]);

it('skips manifests that are missing, too big to be real, or not json', function (Closure $response): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/*' => $response,
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe([]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'raw.githubusercontent.com') && $request->hasHeader('Range', 'bytes=0-102399'));
    Http::assertSentCount(4);
})->with([
    'missing' => fn (): Closure => fn () => Http::response('404: Not Found', 404),
    'cut short' => fn (): Closure => fn () => Http::response('{"require": {"laravel/framework": "^13', 206),
    'a list' => fn (): Closure => fn () => Http::response(['laravel/framework']),
    'unreachable' => fn (): Closure => fn () => Http::failedConnection(),
]);

it('leaves out packages that are not developed on github', function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['acme/private' => '*', 'acme/gitlab' => '*']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response(['dependencies' => ['left-pad' => '*', 'right-pad' => '*', 'up-pad' => '*']]),
        'repo.packagist.org/p2/acme/private.json' => Http::response('', 404),
        'repo.packagist.org/p2/acme/gitlab.json' => Http::response(packagistMetadata('acme/gitlab', 'https://gitlab.com/acme/gitlab.git')),
        'registry.npmjs.org/left-pad/latest' => Http::response(['name' => 'left-pad']),
        'registry.npmjs.org/right-pad/latest' => Http::response(['repository' => ['type' => 'svn']]),
        'registry.npmjs.org/up-pad/latest' => Http::response(['repository' => 'https://github.com/../acme']),
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe([]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/contributors'));
});

it('remembers the people behind a package for a day', function (): void {
    Http::fake([
        'api.github.com/users/*/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/*/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework')),
        'api.github.com/repos/laravel/framework/contributors*' => Http::response(githubContributors([1])),
    ]);

    $query = resolve(DependencyContributorsQuery::class);

    expect($query->get('jess'))->toBe(['1'])
        ->and($query->get('jess'))->toBe(['1']);

    $this->travel(1)->days();

    expect($query->get('jess'))->toBe(['1'])
        ->and(Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://repo.packagist.org')))->toHaveCount(2)
        ->and(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/contributors')))->toHaveCount(2);
});

it('asks again about a package whose lookup failed', function (int $packagist, int $contributors): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::sequence()
            ->push(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git'), $packagist)
            ->push(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'api.github.com/repos/laravel/framework/contributors*' => Http::sequence()
            ->push(githubContributors([1]), $contributors)
            ->push(githubContributors([1])),
    ]);

    $query = resolve(DependencyContributorsQuery::class);

    expect($query->get('jess'))->toBe([])
        ->and($query->get('jess'))->toBe(['1']);
})->with([
    'where it is developed' => [503, 200],
    'who develops it' => [200, 500],
    'who develops it, while rate limited' => [200, 403],
]);

it('stops looking after twenty seconds', function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([
            ['full_name' => 'jess/one', 'fork' => false],
            ['full_name' => 'jess/two', 'fork' => false],
        ]),
        'raw.githubusercontent.com/jess/one/HEAD/composer.json' => function (): PromiseInterface {
            $this->travel(20)->seconds();

            return Http::response(['require' => ['laravel/framework' => '^13.0']]);
        },
        'raw.githubusercontent.com/*' => Http::response('404: Not Found', 404),
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe([]);

    Http::assertSentCount(3);
});

it('keeps who it found before the twenty seconds ran out', function (): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0', 'pestphp/pest' => '^5.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'repo.packagist.org/p2/pestphp/pest.json' => Http::response(packagistMetadata('pestphp/pest', 'https://github.com/pestphp/pest.git')),
        'api.github.com/repos/laravel/framework/contributors*' => function (): PromiseInterface {
            $this->travel(20)->seconds();

            return Http::response(githubContributors([1]));
        },
    ]);

    expect(resolve(DependencyContributorsQuery::class)->get('jess'))->toBe(['1']);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'pestphp/pest/contributors'));
});

it('leaves out repositories github no longer has, that have no history, or too much of it, without asking again', function (int $status, ?array $body): void {
    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'api.github.com/users/jess/starred*' => Http::response([]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagistMetadata('laravel/framework', 'https://github.com/laravel/framework.git')),
        'api.github.com/repos/laravel/framework/contributors*' => Http::response($body, $status),
    ]);

    $query = resolve(DependencyContributorsQuery::class);

    expect($query->get('jess'))->toBe([])
        ->and($query->get('jess'))->toBe([])
        ->and(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/contributors')))->toHaveCount(1);
})->with([
    'gone' => [404, null],
    'empty' => [204, null],
    'too large' => [403, ['message' => 'The history or contributor list is too large to list contributors for this repository via the API.']],
]);

it('fails when github cannot list the repositories or the stars or throttles reading them, so the lookup is tried again', function (array $responses): void {
    Http::fake($responses);

    expect(fn () => resolve(DependencyContributorsQuery::class)->get('jess'))->toThrow(RequestException::class);
})->with([
    'listing' => fn (): array => ['api.github.com/users/jess/repos*' => Http::response(['message' => 'Server Error'], 500)],
    'starring' => fn (): array => [
        'api.github.com/users/jess/repos*' => Http::response([]),
        'api.github.com/users/jess/starred*' => Http::response(['message' => 'Server Error'], 500),
    ],
    'reading' => fn (): array => [
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'raw.githubusercontent.com/*' => Http::response('429: Too Many Requests', 429),
    ],
]);
