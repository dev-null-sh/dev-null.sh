<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dto\GitHubSponsors;
use App\Dto\GitHubSponsorship;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class GitHubSponsorsQuery
{
    private const int MaxPages = 10;

    private const string Query = <<<'GRAPHQL'
        query ($sponsoring: Boolean!, $sponsoringAfter: String, $sponsors: Boolean!, $sponsorsAfter: String) {
          viewer {
            hasSponsorsListing
            sponsoring: sponsorshipsAsSponsor(first: 100, after: $sponsoringAfter, activeOnly: false) @include(if: $sponsoring) {
              pageInfo { hasNextPage endCursor }
              nodes { ...sponsorship account: sponsorable { ... on User { databaseId } } }
            }
            sponsors: sponsorshipsAsMaintainer(first: 100, after: $sponsorsAfter, activeOnly: false, includePrivate: true) @include(if: $sponsors) {
              pageInfo { hasNextPage endCursor }
              nodes { ...sponsorship account: sponsorEntity { ... on User { databaseId } } }
            }
          }
        }

        fragment sponsorship on Sponsorship { createdAt tierSelectedAt isActive isOneTimePayment }
        GRAPHQL;

    /**
     * The REST API doesn't say, so this asks GraphQL with the user's own token, which is granted read:user for:
     * every field of a sponsorship needs it. Past sponsorships come too, so lapsed sponsors can be told apart from
     * active ones. The user's own sponsors are only asked for when wanted. Null when GitHub can't answer, which keeps
     * what is already known.
     */
    public function get(string $token, string $username, bool $withSponsors): ?GitHubSponsors
    {
        $variables = ['sponsoring' => true, 'sponsoringAfter' => null, 'sponsors' => $withSponsors, 'sponsorsAfter' => null];
        $found = ['sponsoring' => [], 'sponsors' => []];
        $hasSponsorsListing = false;

        for ($page = 0; $page < self::MaxPages && ($variables['sponsoring'] || $variables['sponsors']); $page++) {
            $viewer = $this->viewer($token, $username, $variables);

            if ($viewer === null) {
                return null;
            }

            $hasSponsorsListing = Arr::get($viewer, 'hasSponsorsListing') === true;

            foreach (['sponsoring', 'sponsors'] as $connection) {
                if (! $variables[$connection]) {
                    continue;
                }

                $nodes = Arr::get($viewer, "{$connection}.nodes");

                foreach (is_array($nodes) ? $nodes : [] as $node) {
                    $sponsorship = is_array($node) ? GitHubSponsorship::fromNode($node) : null;

                    if ($sponsorship instanceof GitHubSponsorship) {
                        $found[$connection][] = $sponsorship;
                    }
                }

                $cursor = Arr::get($viewer, "{$connection}.pageInfo.endCursor");

                $variables[$connection] = Arr::get($viewer, "{$connection}.pageInfo.hasNextPage") === true;
                $variables["{$connection}After"] = is_string($cursor) ? $cursor : null;
            }
        }

        return new GitHubSponsors(
            hasSponsorsListing: $hasSponsorsListing,
            sponsoring: $variables['sponsoring'] ? null : $found['sponsoring'],
            sponsors: $withSponsors && ! $variables['sponsors'] ? $found['sponsors'] : null,
        );
    }

    /**
     * @param  array<string, bool|string|null>  $variables
     * @return array<array-key, mixed>|null
     */
    private function viewer(string $token, string $username, array $variables): ?array
    {
        try {
            $response = Http::withToken($token)
                ->connectTimeout(2)
                ->timeout(3)
                ->post('https://api.github.com/graphql', [
                    'query' => self::Query,
                    'variables' => $variables,
                ]);
        } catch (ConnectionException $connectionException) {
            Log::warning('Could not read GitHub Sponsors.', [
                'username' => $username,
                'error' => $connectionException->getMessage(),
            ]);

            return null;
        }

        $viewer = $response->json('data.viewer');

        if ($response->failed() || $response->json('errors') !== null || ! is_array($viewer)) {
            Log::warning('Could not read GitHub Sponsors.', [
                'username' => $username,
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? $response->json('message'),
            ]);

            return null;
        }

        return $viewer;
    }
}
