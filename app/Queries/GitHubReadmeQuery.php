<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class GitHubReadmeQuery
{
    /**
     * Profile READMEs are mostly badges and banners, whose long proxied image URLs add up.
     */
    private const int MaxLength = 200_000;

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * The README of the developer's GitHub profile repository, the one named after them, as GitHub renders it. Its
     * images already go through GitHub's image proxy, so readers never reach the hosts they come from. Cached for a
     * day, a missing README included. Null when there is none, or when GitHub didn't answer, which is not cached.
     */
    public function get(User $user): ?string
    {
        try {
            $html = Cache::remember("github-readme:{$user->github_id}", 86400, function () use ($user): string {
                $login = $this->github->login($user->github_id);

                if ($login === null) {
                    return '';
                }

                $response = $this->github->request()
                    ->accept('application/vnd.github.html+json')
                    ->get("repos/{$login}/{$login}/readme");

                return $response->notFound() ? '' : $this->sanitize($response->throw()->body());
            });
        } catch (HttpClientException) {
            return null;
        }

        return $html === '' ? null : $html;
    }

    /**
     * Never trusted as is: only plain formatting, links and proxied images are kept. Classes, ids and styles are
     * dropped, so a README can't take on the page's own styles, cover it or pose as part of it.
     */
    private function sanitize(string $html): string
    {
        $config = new HtmlSanitizerConfig()
            ->allowSafeElements()
            // GitHub wraps tables and theme-aware images in its own elements: those go, what they hold stays.
            ->blockElement('markdown-accessiblity-table')
            ->blockElement('themed-picture')
            // A source's srcset is not checked against the allowed hosts, so pictures fall back to their image.
            ->dropElement('source')
            ->dropAttribute('class', '*')
            ->dropAttribute('id', '*')
            ->dropAttribute('style', '*')
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->allowMediaSchemes(['https'])
            ->allowMediaHosts(['camo.githubusercontent.com', 'github.com', 'raw.githubusercontent.com', 'user-images.githubusercontent.com', 'private-user-images.githubusercontent.com'])
            ->allowRelativeMedias(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(self::MaxLength);

        return mb_trim(new HtmlSanitizer($config)->sanitize($html));
    }
}
