<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Never trusts a profile README: only plain formatting, links and images from GitHub's hosts are kept. Classes,
 * ids and styles are dropped, so a README can't take on the page's own styles, cover it or pose as part of it.
 *
 * It lives on its own so the rules can be exercised directly, against the payloads that have broken sanitizers
 * before, without going near GitHub or the database.
 */
final readonly class GitHubReadmeSanitizer
{
    /**
     * Profile READMEs are mostly badges and banners, whose long proxied image URLs add up.
     */
    private const int MAX_LENGTH = 200_000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer($this->config());
    }

    public function sanitize(string $html): string
    {
        return mb_trim($this->sanitizer->sanitize($html));
    }

    private function config(): HtmlSanitizerConfig
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

        return $config
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->allowMediaSchemes(['https'])
            ->allowMediaHosts(['camo.githubusercontent.com', 'github.com', 'raw.githubusercontent.com', 'user-images.githubusercontent.com', 'private-user-images.githubusercontent.com'])
            ->allowRelativeMedias(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(self::MAX_LENGTH);
    }
}
