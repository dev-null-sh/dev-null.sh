<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class IssueSuggestionsQuery
{
    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * GitHub's issues endpoint also returns pull requests; their numbers share a single sequence. A reference GitHub
     * couldn't find is cached too, so asking for it again doesn't spend another request.
     *
     * @return array{number: int, title: string, url: string}|null
     */
    public function get(int $number): ?array
    {
        $repository = config()->string('services.github.repository');

        /** @var array{number: int, title: string, url: string}|false $issue */
        $issue = Cache::remember("issue-suggestion:{$repository}:{$number}", 300, function () use ($repository, $number): array|false {
            try {
                return $this->github->issue($repository, $number) ?? false;
            } catch (HttpClientException) {
                return false;
            }
        });

        return $issue === false ? null : $issue;
    }
}
