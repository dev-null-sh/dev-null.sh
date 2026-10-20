<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Suggests GitHub issues and pull requests on this repository for a "#123" composer reference.
 *
 * @phpstan-type IssueReference array{number: int, title: string, url: string, kind: 'issue'|'pull'}
 */
final readonly class IssueReferenceSuggestionsQuery
{
    private const int Limit = 6;

    private const int CacheSeconds = 60;

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * Exact numbers prefer the issue or PR with that number. Prefix digits also match other numbers that start the
     * same way, so "#72" can offer 72, 720, and 721. Cached briefly so typing does not burn the GitHub rate limit.
     *
     * @return list<IssueReference>
     */
    public function get(string $digits): array
    {
        if (preg_match('/^\d{1,7}$/', $digits) !== 1) {
            return [];
        }

        /** @var list<IssueReference> $references */
        $references = Cache::remember(
            'issue-references:v1:'.$digits,
            self::CacheSeconds,
            fn (): array => $this->fetch($digits),
        );

        return $references;
    }

    /**
     * @return list<IssueReference>
     */
    private function fetch(string $digits): array
    {
        $byNumber = [];

        try {
            $exact = $this->github->request()->get('repos/'.ChangelogQuery::Repository.'/issues/'.$digits);

            if ($exact->successful()) {
                /** @var array{number: int, title: string, html_url: string, pull_request?: array<string, mixed>} $payload */
                $payload = $exact->json();
                $reference = $this->map($payload);
                $byNumber[$reference['number']] = $reference;
            }
        } catch (HttpClientException) {
            //
        }

        try {
            $search = $this->github->request()->get('search/issues', [
                'q' => 'repo:'.ChangelogQuery::Repository.' '.$digits,
                'per_page' => 10,
            ])->throw();

            if ($search->fluent()->boolean('incomplete_results')) {
                return array_values($byNumber);
            }

            /** @var list<array{number: int, title: string, html_url: string, pull_request?: array<string, mixed>}> $items */
            $items = $search->json('items') ?? [];

            foreach ($items as $item) {
                if (! str_starts_with((string) $item['number'], $digits)) {
                    continue;
                }

                $reference = $this->map($item);
                $byNumber[$reference['number']] ??= $reference;
            }
        } catch (HttpClientException) {
            //
        }

        $references = array_values($byNumber);

        usort($references, function (array $left, array $right) use ($digits): int {
            $leftExact = (string) $left['number'] === $digits ? 0 : 1;
            $rightExact = (string) $right['number'] === $digits ? 0 : 1;

            return $leftExact <=> $rightExact
                ?: $left['number'] <=> $right['number'];
        });

        return array_slice($references, 0, self::Limit);
    }

    /**
     * @param  array{number: int, title: string, html_url: string, pull_request?: array<string, mixed>}  $item
     * @return IssueReference
     */
    private function map(array $item): array
    {
        return [
            'number' => $item['number'],
            'title' => Str::of($item['title'])->substr(0, 200)->value(),
            'url' => $item['html_url'],
            'kind' => array_key_exists('pull_request', $item) ? 'pull' : 'issue',
        ];
    }
}
