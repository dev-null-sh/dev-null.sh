<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

final readonly class RepositoryHistoryQuery
{
    public function __construct(private GitHub $github)
    {
        //
    }

    /** @return array<string, mixed>|null */
    public function get(string $query): ?array
    {
        $repository = config()->string('services.github.repository');
        $terms = str($query)->explode(' ')->map(fn (string $term): string => '"'.$term.'"')->implode(' ');

        $result = Cache::remember("repository-history:v2:{$repository}:".hash('sha256', $query), 900, function () use ($repository, $terms): ?array {
            try {
                $pullRequests = $this->github->request()->get('search/issues', [
                    'q' => 'repo:'.$repository.' is:pr is:merged base:'.ChangelogQuery::Branch.' in:title,body '.$terms,
                    'per_page' => 5,
                ])->throw();
                $commits = $this->github->request()->get('search/commits', [
                    'q' => 'repo:'.$repository.' merge:false '.$terms,
                    'per_page' => 5,
                ])->throw();

                if ($pullRequests->fluent()->boolean('incomplete_results') || $commits->fluent()->boolean('incomplete_results')) {
                    return null;
                }
            } catch (HttpClientException) {
                return null;
            }

            return [
                'repository' => $repository,
                'as_of' => now()->toIso8601String(),
                'freshness' => 'Cached for 15 minutes. GitHub search indexing can lag.',
                'complete' => false,
                'coverage' => 'Up to five best matches each: merged production PR titles and descriptions, and non-merge Git commit messages on the default branch, across all indexed dates. All keywords must match, in any order; this is lexical search, not semantic, code or diff search. No matches does not mean the feature does not exist. Results may be follow-up fixes rather than the original implementation.',
                'attribution' => 'PR authors opened the PR; commit authors are GitHub-linked author accounts, not committers or mergers. Neither proves sole implementation or includes all co-authors. A null account is unknown; never infer identity from names or emails. Cite the matching PR or commit, distinguish these roles, and state uncertainty when the evidence is inconclusive. A merge is not proof of deployment.',
                'pull_requests' => $pullRequests->collect('items')->take(5)->mapInto(Fluent::class)->map(fn (Fluent $pullRequest): array => [
                    'title' => $pullRequest->string('title')->substr(0, 300)->value(),
                    'url' => $pullRequest->string('html_url')->value(),
                    'pr_author' => $pullRequest->get('user.login'),
                    'github_id' => $pullRequest->get('user.id'),
                    'merged_at' => $pullRequest->get('pull_request.merged_at'),
                ])->all(),
                'commits' => $commits->collect('items')->take(5)->mapInto(Fluent::class)->map(fn (Fluent $commit): array => [
                    'title' => $commit->string('commit.message')->before("\n")->substr(0, 300)->value(),
                    'url' => $commit->string('html_url')->value(),
                    'commit_author' => $commit->get('author.login'),
                    'github_id' => $commit->get('author.id'),
                    'authored_at' => $commit->get('commit.author.date'),
                ])->all(),
            ];
        });

        if ($result === null) {
            return null;
        }

        $authors = User::query()
            ->whereNotNull('onboarded_at')
            ->whereIn('github_id', collect($result['pull_requests'])->merge($result['commits'])->pluck('github_id')->filter()->unique())
            ->get(['github_id', 'username'])
            ->reject(fn (User $user): bool => str_starts_with($user->username, '_'))
            ->mapWithKeys(fn (User $user): array => [$user->github_id => $user->mention()]);

        foreach (['pull_requests', 'commits'] as $key) {
            $result[$key] = collect($result[$key])->mapInto(Fluent::class)->map(fn (Fluent $item): array => [
                ...$item->except('github_id'),
                'mention' => $authors->get($item->string('github_id')->value()),
            ])->all();
        }

        return $result;
    }
}
