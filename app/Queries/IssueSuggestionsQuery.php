<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class IssueSuggestionsQuery
{
    public function __construct(private GitHub $github)
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
                $issue = $this->github->request()
                    ->get("repos/{$repository}/issues/{$number}")
                    ->throw()
                    ->fluent();
            } catch (HttpClientException) {
                return false;
            }

            return [
                'number' => $issue->integer('number'),
                'title' => $issue->string('title')->value(),
                'url' => $issue->string('html_url')->value(),
            ];
        });

        return $issue === false ? null : $issue;
    }
}
