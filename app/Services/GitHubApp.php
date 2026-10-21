<?php

declare(strict_types=1);

namespace App\Services;

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
