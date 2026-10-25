<?php

declare(strict_types=1);

namespace App\Dto;

use Uri\WhatWg\Url;

final readonly class GitHubPermalink
{
    /**
     * An owner, a repository, a full commit SHA (a branch or a tag would drift from the code that was cited), and a
     * path of plain segments. The URL parser has already collapsed any "." and ".." segments by then. Keep the owner in
     * sync with resources/js/components/linkified-text.tsx.
     */
    private const string PATH_PATTERN = '#^/([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})/([A-Za-z0-9._-]{1,100})/blob/([0-9a-f]{40})/([A-Za-z0-9._@+-]+(?:/[A-Za-z0-9._@+-]+)*)$#';

    private const string LINES_PATTERN = '/^L([1-9]\d{0,6})(?:-L([1-9]\d{0,6}))?$/';

    private function __construct(
        public string $owner,
        public string $repository,
        public string $sha,
        public string $path,
        public int $startLine,
        public int $endLine,
    ) {
        //
    }

    /**
     * Returns the permalink behind a link to lines of a file at a commit on GitHub, like
     * https://github.com/owner/repo/blob/<sha>/src/Foo.php#L10-L20, or null for any other URL.
     */
    public static function parse(string $url): ?self
    {
        $parsed = Url::parse($url);

        if (! $parsed instanceof Url
            || ! in_array($parsed->getScheme(), ['http', 'https'], true)
            || ! in_array($parsed->getAsciiHost(), ['github.com', 'www.github.com'], true)
            || ($parsed->getUsername() ?? '') !== ''
            || ($parsed->getPassword() ?? '') !== ''
            || $parsed->getPort() !== null
            || preg_match(self::PATH_PATTERN, $parsed->getPath(), $path) !== 1
            || preg_match(self::LINES_PATTERN, $parsed->getFragment() ?? '', $lines) !== 1) {
            return null;
        }

        $startLine = (int) $lines[1];
        $endLine = isset($lines[2]) ? (int) $lines[2] : $startLine;

        return $endLine < $startLine ? null : new self($path[1], $path[2], $path[3], $path[4], $startLine, $endLine);
    }

    public function rawUrl(): Url
    {
        return new Url("https://raw.githubusercontent.com/{$this->owner}/{$this->repository}/{$this->sha}/{$this->path}");
    }

    /**
     * The same lines get the same key however the link was written (http, www, a query string).
     */
    public function cacheKey(): string
    {
        return "code-snippet:{$this->owner}/{$this->repository}/{$this->sha}/{$this->path}#L{$this->startLine}-L{$this->endLine}";
    }
}
