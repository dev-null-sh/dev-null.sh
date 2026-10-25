<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Acts as the GitHub App installed on this repository only. Its installation tokens are limited to writing issues
 * there, so no user ever grants access to their own repositories.
 */
final readonly class GitHubApp
{
    private const int CommentsPerPage = 100;

    private const int MaxCommentRequests = 10;

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * @param  list<string>  $labels
     * @return array{number: int, url: string}
     */
    public function createIssue(string $title, string $body, array $labels): array
    {
        $issue = $this->github->request($this->installationToken())
            ->post('repos/'.config()->string('services.github.repository').'/issues', [
                'title' => $title,
                'body' => $body,
                'labels' => $labels,
            ])
            ->throw()
            ->json();

        assert(is_array($issue) && is_int($issue['number']) && is_string($issue['html_url']));

        return ['number' => $issue['number'], 'url' => $issue['html_url']];
    }

    /**
     * The comments on the repository's issues created or edited since then, oldest edit first: one listing for the whole
     * repository rather than one request per issue. Each request starts again from the last edit it has seen, so a comment
     * edited while the listing runs moves to its end instead of pushing another out of reach between two pages. Requests
     * overlap by a second, as edits are timed to the second, and a page of comments all edited within it moves on by page
     * instead. A short page is the last; after the cap the listing stops where it got to, so the next one carries on from
     * there rather than skipping what is left. Entries that aren't comments as described are left out instead of stopping
     * the listing, and a comment whose author GitHub no longer shows, as after their account is deleted, has none.
     *
     * @return array{comments: list<array{id: int, url: string, issue: int, author_id: string|null, is_bot: bool}>, until: CarbonImmutable|null}
     */
    public function issueComments(CarbonInterface $since): array
    {
        $comments = [];
        $cursor = CarbonImmutable::instance($since);
        $page = 1;

        for ($request = 1; $request <= self::MaxCommentRequests; $request++) {
            $batch = $this->github->request($this->installationToken())
                ->get('repos/'.config()->string('services.github.repository').'/issues/comments', [
                    'since' => $cursor->toIso8601ZuluString(),
                    'sort' => 'updated',
                    'direction' => 'asc',
                    'per_page' => self::CommentsPerPage,
                    'page' => $page,
                ])
                ->throw()
                ->json();

            $batch = is_array($batch) ? array_values($batch) : [];
            $lastEdit = $cursor;

            foreach ($batch as $comment) {
                if (! is_array($comment)
                    || ! is_int($comment['id'] ?? null)
                    || ! is_string($comment['html_url'] ?? null)
                    || ! is_string($comment['issue_url'] ?? null)
                    || ! is_string($comment['updated_at'] ?? null)) {
                    continue;
                }

                $author = is_array($comment['user'] ?? null) ? $comment['user'] : [];
                $editedAt = CarbonImmutable::parse($comment['updated_at']);
                $lastEdit = $editedAt->greaterThan($lastEdit) ? $editedAt : $lastEdit;

                $comments[] = [
                    'id' => $comment['id'],
                    'url' => $comment['html_url'],
                    'issue' => (int) Str::afterLast($comment['issue_url'], '/'),
                    'author_id' => is_int($author['id'] ?? null) ? (string) $author['id'] : null,
                    'is_bot' => ($author['type'] ?? null) === 'Bot',
                ];
            }

            if (count($batch) < self::CommentsPerPage) {
                return ['comments' => $comments, 'until' => null];
            }

            $next = $lastEdit->subSecond();

            [$cursor, $page] = $next->greaterThan($cursor) ? [$next, 1] : [$cursor, $page + 1];
        }

        return ['comments' => $comments, 'until' => $cursor];
    }

    /**
     * An installation token lasts an hour, so it is reused for 50 minutes, encrypted at rest in the cache.
     */
    private function installationToken(): string
    {
        $repository = config()->string('services.github.repository');
        $token = Cache::get("github-app:{$repository}:installation-token");

        if (is_string($token)) {
            return Crypt::decryptString($token);
        }

        $jwt = $this->jwt();

        $installation = $this->github->request($jwt)
            ->get("repos/{$repository}/installation")
            ->throw()
            ->json('id');

        assert(is_int($installation));

        $token = $this->github->request($jwt)
            ->post("app/installations/{$installation}/access_tokens", [
                'repositories' => [Str::after($repository, '/')],
                'permissions' => ['issues' => 'write'],
            ])
            ->throw()
            ->json('token');

        assert(is_string($token));

        Cache::put("github-app:{$repository}:installation-token", Crypt::encryptString($token), now()->addMinutes(50));

        return $token;
    }

    /**
     * The app authenticates with a short-lived JWT, backdated a minute to allow for clock drift with GitHub.
     */
    private function jwt(): string
    {
        return JWT::encode([
            'iat' => now()->subMinute()->getTimestamp(),
            'exp' => now()->addMinutes(9)->getTimestamp(),
            'iss' => config()->string('services.github_app.id'),
        ], str_replace('\n', "\n", config()->string('services.github_app.private_key')), 'RS256');
    }
}
