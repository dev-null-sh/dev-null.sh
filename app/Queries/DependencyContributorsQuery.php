<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

final readonly class DependencyContributorsQuery
{
    /**
     * The most recently pushed repositories read, so a prolific account can't use up the rate limit on its own.
     */
    private const int Repositories = 10;

    /**
     * The packages looked up, the ones the most repositories depend on first.
     */
    private const int Packages = 20;

    /**
     * The top contributors to a package's repository who count as the people behind it.
     */
    private const int Contributors = 5;

    /**
     * How long reading and looking up may take altogether. The people behind someone's stack are suggested while they
     * find their feet, so whatever isn't looked up by then is left out rather than waited for.
     */
    private const int Seconds = 20;

    /**
     * A GitHub repository as registries list one: an https, git or ssh URL, a github: one, or npm's owner/name shorthand.
     * Neither part can start with a dot, so a listed repository can't point anywhere else on GitHub's API.
     */
    private const string RepositoryUrl = '~^(?:(?:git\+)?(?:https?|git|ssh)://(?:git@)?(?:www\.)?github\.com/|git@github\.com:|github:)?(?<repository>[\w-][\w.-]{0,38}/[\w-][\w.-]{0,99}?)(?:\.git)?/?(?:#.*)?$~i';

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * The GitHub IDs of the people behind a user's stack: the top contributors to the repositories of the Composer and
     * npm packages their own public repositories depend on, read from the composer.json and package.json at their root.
     *
     * @return array<int, string>
     */
    public function get(string $login): array
    {
        $deadline = now()->addSeconds(self::Seconds);
        $inTime = fn (): bool => now()->isBefore($deadline);

        return $this->github->request()
            ->get("users/{$login}/repos", ['type' => 'owner', 'sort' => 'pushed', 'per_page' => self::Repositories])
            ->throw()
            ->collect()
            ->mapInto(Fluent::class)
            ->reject(fn (Fluent $repository): bool => $repository->boolean('fork'))
            ->flatMap(fn (Fluent $repository): array => $inTime() ? $this->dependencies($repository->string('full_name')->value()) : [])
            ->countBy()
            ->sortDesc()
            ->take(self::Packages)
            ->keys()
            ->map(fn (string $package): ?string => $inTime() ? $this->repository($package) : null)
            ->filter()
            ->flatMap(fn (string $repository): array => $inTime() ? $this->contributors($repository) : [])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The packages a repository depends on, in and out of development, as "composer:vendor/name" and "npm:name".
     *
     * @return list<string>
     */
    private function dependencies(string $repository): array
    {
        return [
            ...$this->packages('composer', $this->manifest($repository, 'composer.json'), ['require', 'require-dev']),
            ...$this->packages('npm', $this->manifest($repository, 'package.json'), ['dependencies', 'devDependencies']),
        ];
    }

    /**
     * The package names in the given sections of a manifest. Platform requirements such as php and ext-json have no
     * vendor, so Composer's naming rules leave them out along with anything that isn't a package name. Types from @types
     * are left out too: DefinitelyTyped's contributors wrote them, not the people behind the packages they describe.
     *
     * @param  'composer'|'npm'  $registry
     * @param  Fluent<array-key, mixed>  $manifest
     * @param  list<string>  $sections
     * @return array<int, string>
     */
    private function packages(string $registry, Fluent $manifest, array $sections): array
    {
        $pattern = match ($registry) {
            'composer' => '#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#',
            'npm' => '#^(@[a-z0-9-~][a-z0-9-._~]*/)?[a-z0-9-~][a-z0-9-._~]*$#',
        };

        return collect($sections)
            ->flatMap(fn (string $section): array => array_keys($manifest->array($section)))
            ->filter(fn (int|string $name): bool => preg_match($pattern, (string) $name) === 1)
            ->reject(fn (int|string $name): bool => Str::startsWith((string) $name, '@types/'))
            ->map(fn (int|string $name): string => "{$registry}:{$name}")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A manifest at the root of a repository's default branch, empty when there is none. Only the first 100 KB are
     * asked for: a real manifest is far smaller, and a bigger one stops being valid JSON once cut, so it is skipped.
     * When GitHub throttles the reads, the lookup fails so it is tried again later, instead of quietly finding nobody.
     *
     * @return Fluent<array-key, mixed>
     */
    private function manifest(string $repository, string $file): Fluent
    {
        try {
            $manifest = $this->http()
                ->withHeaders(['Range' => 'bytes=0-102399'])
                ->get("https://raw.githubusercontent.com/{$repository}/HEAD/{$file}")
                ->throwIfStatus(429)
                ->json();
        } catch (ConnectionException) {
            return new Fluent;
        }

        return new Fluent(is_array($manifest) ? $manifest : []);
    }

    /**
     * The GitHub repository a package is developed in, like "laravel/framework", as its registry lists it. A package
     * without one is remembered too, so it doesn't cost the next person a request; a failed request isn't, so it's retried.
     */
    private function repository(string $package): ?string
    {
        try {
            $repository = Cache::remember('dependency-repository:'.hash('sha256', $package), 86400, function () use ($package): string|false {
                $name = Str::after($package, ':');

                if (Str::startsWith($package, 'composer:')) {
                    $url = data_get($this->registry("https://repo.packagist.org/p2/{$name}.json"), ['packages', $name, 0, 'source', 'url']);
                } else {
                    $document = $this->registry('https://registry.npmjs.org/'.str_replace('/', '%2f', $name).'/latest');

                    // npm lists the repository either as an object with a URL or as the URL on its own.
                    $url = data_get($document, 'repository.url', data_get($document, 'repository'));
                }

                return is_string($url) && preg_match(self::RepositoryUrl, $url, $matches) === 1 ? $matches['repository'] : false;
            });
        } catch (HttpClientException) {
            return null;
        }

        return $repository === false ? null : $repository;
    }

    /**
     * The GitHub IDs of the people among a repository's top contributors, remembered for a day so that everyone whose
     * packages come from it shares one request. A repository GitHub no longer has, or one without history, has none.
     *
     * @return array<int, string>
     */
    private function contributors(string $repository): array
    {
        try {
            return Cache::remember("dependency-contributors:{$repository}", 86400, function () use ($repository): array {
                $response = $this->github->request()->get("repos/{$repository}/contributors", ['per_page' => self::Contributors]);

                if ($response->notFound() || $response->noContent()) {
                    return [];
                }

                return $response->throw()
                    ->collect()
                    ->where('type', 'User')
                    ->mapInto(Fluent::class)
                    ->map(fn (Fluent $contributor): string => $contributor->string('id')->value())
                    ->values()
                    ->all();
            });
        } catch (HttpClientException) {
            return [];
        }
    }

    /**
     * A registry's document about a package, empty when the registry doesn't know it.
     *
     * @return array<array-key, mixed>
     */
    private function registry(string $url): array
    {
        $response = $this->http()->get($url);

        return $response->notFound() ? [] : (array) $response->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent(sprintf('devnull/1.0 (+%s)', config()->string('app.url')))->connectTimeout(3)->timeout(5);
    }
}
