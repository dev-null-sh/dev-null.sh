<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class GitHubReadmeQuery
{
    /**
     * Profile READMEs are mostly badges and banners, whose long proxied image URLs add up.
     */
    private const int MaxLength = 200_000;

    /**
     * Images uploaded to GitHub are served from links signed for five minutes.
     */
    private const int SignedImagesTtl = 240;

    private const int FailureTtl = 300;

    public function __construct(private GitHubProvider $github)
    {
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

        $html = $this->github->readme($login);

        return $html === null ? '' : $this->sanitize($html);
    }

    /**
     * Never trusted as is: only plain formatting, links and images from GitHub's hosts are kept. Classes, ids and
     * styles are dropped, so a README can't take on the page's own styles, cover it or pose as part of it.
     */
    private function sanitize(string $html): string
    {
        $config = new HtmlSanitizerConfig()
            // Only what a README is written with: a fixed list, so attributes like srcset or name, and elements like
            // dialog, never come along with the browser's idea of what is safe.
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'align'])
            ->allowElement('p', ['align'])
            ->allowElement('div', ['align'])
            ->allowElement('h1', ['align'])
            ->allowElement('h2', ['align'])
            ->allowElement('h3', ['align'])
            ->allowElement('h4', ['align'])
            ->allowElement('h5', ['align'])
            ->allowElement('h6', ['align'])
            ->allowElement('th', ['align', 'colspan', 'rowspan'])
            ->allowElement('td', ['align', 'colspan', 'rowspan'])
            ->allowElement('ol', ['start'])
            ->allowElement('details', ['open']);

        foreach (['article', 'span', 'ul', 'li', 'table', 'thead', 'tbody', 'tr', 'pre', 'code', 'blockquote', 'summary', 'picture', 'br', 'hr', 'b', 'strong', 'i', 'em', 'sub', 'sup', 'del', 's', 'kbd'] as $element) {
            $config = $config->allowElement($element);
        }

        // Any other element goes, but what it holds stays: GitHub's own wrappers like g-emoji or themed-picture, and
        // the rarer text elements like samp or dl, keep their words. Only these go with everything in them.
        $config = $config->defaultAction(HtmlSanitizerAction::Block);

        foreach (['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'dialog', 'map', 'form', 'textarea', 'select', 'button', 'head', 'title', 'source', 'video', 'audio'] as $element) {
            $config = $config->dropElement($element);
        }

        $config = $config
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
