<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use App\Services\GitHubReadmeSanitizer;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class GitHubReadmeQuery
{
    /**
     * Images uploaded to GitHub are served from links signed for five minutes.
     */
    private const int SignedImagesTtl = 240;

    private const int FailureTtl = 300;

    public function __construct(
        private GitHub $github,
        private GitHubReadmeSanitizer $sanitizer,
    ) {
        //
    }

    /**
     * The README of the developer's GitHub profile repository, the one named after them, as GitHub renders it. Its
     * images come from GitHub's own hosts, so readers never reach the hosts they come from. Cached for a day, a missing
     * README included, but only for a few minutes when it holds uploaded images, whose signed links expire after five.
     * Null when there is none, false when GitHub didn't answer, which is remembered for a few minutes so an outage
     * doesn't call GitHub again on every view.
     */
    public function get(User $user): string|false|null
    {
        $key = $this->key($user);
        $failedKey = $this->failedKey($user);

        /** @var string|null $html */
        $html = Cache::get($key);

        if ($html === null) {
            if (Cache::has($failedKey)) {
                return false;
            }

            try {
                $html = $this->fetch($user);
            } catch (HttpClientException) {
                Cache::put($failedKey, true, self::FailureTtl);

                return false;
            }

            Cache::put($key, $html, str_contains($html, 'private-user-images.githubusercontent.com') ? self::SignedImagesTtl : 86400);
        }

        return $html === '' ? null : $html;
    }

    /**
     * Read the README from GitHub again on the next view, as someone who syncs their profile expects to see theirs.
     */
    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
        Cache::forget($this->failedKey($user));
    }

    private function key(User $user): string
    {
        return "github-readme:{$user->github_id}";
    }

    private function failedKey(User $user): string
    {
        return "github-readme-failed:{$user->github_id}";
    }

    /**
     * @throws HttpClientException
     */
    private function fetch(User $user): string
    {
        $login = $this->github->login($user->github_id);

        if ($login === null) {
            return '';
        }

        $response = $this->github->request()
            ->replaceHeaders(['Accept' => 'application/vnd.github.html+json'])
            ->get("repos/{$login}/{$login}/readme");

        return $response->notFound() ? '' : $this->sanitizer->sanitize($response->throw()->body());
    }
}
