<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\GitHubReferenceType;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class GitHubReference
{
    private const string Repository = '^/(?<owner>[A-Za-z0-9][A-Za-z0-9-]{0,38})/(?<repository>[\w.-]{1,100})';

    /**
     * The paths on github.com that get a GitHub preview. Any other GitHub link, like a profile, is a regular link.
     *
     * @var array<string, GitHubReferenceType>
     */
    private const array Paths = [
        '~'.self::Repository.'/?$~' => GitHubReferenceType::Repository,
        '~'.self::Repository.'/issues/(?<id>\d+)/?$~' => GitHubReferenceType::Issue,
        '~'.self::Repository.'/pull/(?<id>\d+)(?:/[a-z]+)?/?$~' => GitHubReferenceType::PullRequest,
        '~'.self::Repository.'/releases/tag/(?<id>[^/]+)$~' => GitHubReferenceType::Release,
        '~'.self::Repository.'/commit/(?<id>[0-9a-fA-F]{7,40})/?$~' => GitHubReferenceType::Commit,
    ];

    /**
     * A file at a branch, tag or commit. It only gets a preview when the link points at lines, like #L10 or #L10-L20.
     */
    private const string Code = '~'.self::Repository.'/blob/(?<id>[^/]+)/(?<path>.+)$~';

    private const string Lines = '~^L(?<first>\d+)(?:-L(?<last>\d+))?$~';

    /**
     * Extensions that the highlighter knows by another name. Any other extension is used as the language as is.
     *
     * @var array<string, string>
     */
    private const array LanguagesByExtension = [
        'yml' => 'yaml',
        'jsx' => 'js',
        'mjs' => 'js',
        'cjs' => 'js',
        'tsx' => 'ts',
        'htm' => 'html',
    ];

    private function __construct(
        public GitHubReferenceType $type,
        public string $owner,
        public string $repository,
        public ?string $id,
        public ?string $path = null,
        public ?int $firstLine = null,
        public ?int $lastLine = null,
    ) {
        //
    }

    /**
     * Recognizes a link to a repository, issue, pull request, release, commit or lines of code on github.com.
     */
    public static function tryFromUrl(string $url): ?self
    {
        $page = Url::parse($url);

        if (! $page instanceof Url || ! in_array($page->getAsciiHost(), ['github.com', 'www.github.com'], true)) {
            return null;
        }

        if (preg_match(self::Code, $page->getPath(), $file) === 1) {
            if (preg_match(self::Lines, (string) $page->getFragment(), $lines) !== 1) {
                return null;
            }

            $firstLine = (int) $lines['first'];
            $lastLine = isset($lines['last']) ? (int) $lines['last'] : $firstLine;

            return new self(GitHubReferenceType::Code, $file['owner'], $file['repository'], $file['id'], $file['path'], $firstLine, max($firstLine, $lastLine));
        }

        foreach (self::Paths as $pattern => $type) {
            if (preg_match($pattern, $page->getPath(), $matches) === 1) {
                return new self($type, $matches['owner'], $matches['repository'], $matches['id'] ?? null);
            }
        }

        return null;
    }

    /**
     * Writes the reference the way GitHub does: laravel/framework, laravel/framework#5123, laravel/framework@v13.0.0
     * or laravel/framework@42faad4. Code is labelled with its repository, and its file is the preview's title.
     */
    public function label(): string
    {
        return "{$this->owner}/{$this->repository}".match ($this->type) {
            GitHubReferenceType::Repository, GitHubReferenceType::Code => '',
            GitHubReferenceType::Issue, GitHubReferenceType::PullRequest => "#{$this->id}",
            GitHubReferenceType::Release => '@'.rawurldecode((string) $this->id),
            GitHubReferenceType::Commit => '@'.Str::substr((string) $this->id, 0, 7),
        };
    }

    /**
     * Issues and pull requests share one numbering, so an issue's number may turn out to be a pull request. Code is read
     * from the file's contents, at the branch, tag or commit in $id.
     */
    public function apiPath(): string
    {
        return "repos/{$this->owner}/{$this->repository}".match ($this->type) {
            GitHubReferenceType::Repository => '',
            GitHubReferenceType::Issue => "/issues/{$this->id}",
            GitHubReferenceType::PullRequest => "/pulls/{$this->id}",
            GitHubReferenceType::Release => "/releases/tags/{$this->id}",
            GitHubReferenceType::Commit => "/commits/{$this->id}",
            GitHubReferenceType::Code => "/contents/{$this->path}",
        };
    }

    /**
     * The pull request endpoint, for an issue's number that turns out to be a pull request. Unlike the issues endpoint,
     * it has the size of the change.
     */
    public function pullRequestApiPath(): string
    {
        return "repos/{$this->owner}/{$this->repository}/pulls/{$this->id}";
    }

    /**
     * The file and its lines, like app/Actions/CreateCommit.php#L26-L30.
     */
    public function lines(): string
    {
        $lines = $this->firstLine === $this->lastLine ? "L{$this->firstLine}" : "L{$this->firstLine}-L{$this->lastLine}";

        return rawurldecode((string) $this->path)."#{$lines}";
    }

    /**
     * The language to highlight the file as, which is mostly its extension. The highlighter shows a language it doesn't
     * know as plain text.
     */
    public function language(): ?string
    {
        $file = mb_strtolower(basename(rawurldecode((string) $this->path)));
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match (true) {
            str_ends_with($file, '.blade.php') => 'blade',
            $file === 'dockerfile' => 'dockerfile',
            $extension === '' => null,
            default => self::LanguagesByExtension[$extension] ?? $extension,
        };
    }
}
