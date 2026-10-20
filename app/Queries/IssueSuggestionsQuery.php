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
     * GitHub's issues endpoint also returns pull requests; their numbers share a single sequence.
     *
     * @return array{number: int, title: string, url: string}|null
     */
    public function get(int $number): ?array
    {
        $repository = config()->string('services.github.repository');

        /** @var array{number: int, title: string, url: string}|null */
        return Cache::remember("issue-suggestion:{$repository}:{$number}", 300, function () use ($repository, $number): ?array {
            try {
                $issue = $this->github->request()
                    ->get("repos/{$repository}/issues/{$number}")
                    ->throw()
                    ->fluent();
            } catch (HttpClientException) {
                return null;
            }

            return [
                'number' => $issue->integer('number'),
                'title' => $issue->string('title')->value(),
                'url' => $issue->string('html_url')->value(),
            ];
        });
    }
}
