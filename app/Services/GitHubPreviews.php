<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\GitHubReference;
use App\Enums\GitHubReferenceType;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @phpstan-type Extras array{author?: string, author_on_devnull?: bool, additions?: int, deletions?: int, files?: int, lines?: list<string>, start?: int}
 * @phpstan-type GitHubDetails array{type: string, label: string, state: string|null, stars: int|null, language: string|null, author?: string, author_on_devnull?: bool, additions?: int, deletions?: int, files?: int, lines?: list<string>, start?: int}
 * @phpstan-type Metadata array{title: string|null, description: null, image_url: null, site_name: string, github: GitHubDetails}
 */
final readonly class GitHubPreviews
{
    /**
     * The length of the link preview's title column, ellipsis included.
     */
    private const int TITLE_LENGTH = 300;

    private const int MAX_LINES = 12;

    private const int MAX_LINE_LENGTH = 160;

    private const int MAX_FILE_CHARACTERS = 1_000_000;

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * Describes the reference with GitHub's API rather than the page's Open Graph tags, which can't tell whether an
     * issue is open or a pull request was merged. Returns null when GitHub doesn't know the reference, and throws when
     * it fails in a way that may pass.
     *
     * @return Metadata|null
     */
    public function describe(GitHubReference $reference): ?array
    {
        if ($reference->type === GitHubReferenceType::Code) {
            return $this->code($reference);
        }

        // A commit lists its changed files with their patches, which can be large, so ask for a single one: the
        // commit's stats still cover all of them.
        $data = $this->get($reference->apiPath(), $reference->type === GitHubReferenceType::Commit ? ['per_page' => 1] : []);

        if (! $data instanceof Fluent) {
            return null;
        }

        return match ($reference->type) {
            GitHubReferenceType::Repository => $this->metadata(
                $reference,
                title: $this->firstLine($data, 'description') ?? "{$reference->owner}/{$reference->repository}",
                stars: $data->integer('stargazers_count'),
                language: $this->firstLine($data, 'language'),
            ),
            GitHubReferenceType::Issue => $data->has('pull_request')
                ? $this->pullRequest($reference, $this->pullRequestOrIssue($reference, $data))
                : $this->metadata(
                    $reference,
                    title: $this->firstLine($data, 'title'),
                    state: $this->firstLine($data, 'state'),
                    extras: $this->author($data, 'user'),
                ),
            GitHubReferenceType::PullRequest => $this->pullRequest($reference, $data),
            GitHubReferenceType::Release => $this->metadata(
                $reference,
                title: $this->firstLine($data, 'name') ?? $this->firstLine($data, 'tag_name'),
                state: 'released',
                extras: $this->author($data, 'author'),
            ),
            GitHubReferenceType::Commit => $this->metadata(
                $reference,
                title: $this->firstLine($data, 'commit.message'),
                extras: [
                    ...$this->author($data, 'author'),
                    'additions' => $data->integer('stats.additions'),
                    'deletions' => $data->integer('stats.deletions'),
                ],
            ),
        };
    }

    /**
     * @param  array<string, int>  $query
     * @return Fluent<string, mixed>|null
     */
    private function get(string $path, array $query = []): ?Fluent
    {
        $response = $this->github->request()->get($path, $query)->throwIf($this->isTemporaryFailure(...));

        return $response->successful() ? $response->fluent() : null;
    }

    /**
     * Failures that may pass are thrown, so the job tries again later: server errors and GitHub's rate limits, which
     * answer with a 429, or a 403 once no requests remain.
     */
    private function isTemporaryFailure(Response $response): bool
    {
        return $response->serverError()
            || $response->tooManyRequests()
            || ($response->forbidden() && $response->header('X-RateLimit-Remaining') === '0');
    }

    /**
     * A pull request found by its issue number. The pull request endpoint also has the size of the change, but when
     * that second request fails, the issue is enough for a preview without it.
     *
     * @param  Fluent<string, mixed>  $issue
     * @return Fluent<string, mixed>
     */
    private function pullRequestOrIssue(GitHubReference $reference, Fluent $issue): Fluent
    {
        try {
            return $this->get($reference->pullRequestApiPath()) ?? $issue;
        } catch (HttpClientException) {
            return $issue;
        }
    }

    /**
     * Describes a pull request from either endpoint: the pull request one has merged_at and the size of the change, the
     * issues one has pull_request.merged_at and no size.
     *
     * @param  Fluent<string, mixed>  $data
     * @return Metadata
     */
    private function pullRequest(GitHubReference $reference, Fluent $data): array
    {
        $isMerged = $data->filled('merged_at') || $data->filled('pull_request.merged_at');

        return $this->metadata(
            $reference,
            title: $this->firstLine($data, 'title'),
            type: GitHubReferenceType::PullRequest,
            state: $isMerged ? 'merged' : $this->firstLine($data, 'state'),
            extras: [
                ...$this->author($data, 'user'),
                ...($data->has('additions') ? [
                    'additions' => $data->integer('additions'),
                    'deletions' => $data->integer('deletions'),
                    'files' => $data->integer('changed_files'),
                ] : []),
            ],
        );
    }

    /**
     * Reads the linked lines from the file, at most twelve of them, so the preview shows the code itself. The file is
     * streamed and only read as far as those lines, and never more than a million characters of it.
     *
     * @return Metadata|null
     */
    private function code(GitHubReference $reference): ?array
    {
        $response = $this->github->request()
            ->accept('application/vnd.github.raw+json')
            ->withOptions(['stream' => true])
            ->get($reference->apiPath(), ['ref' => $reference->id])
            ->throwIf($this->isTemporaryFailure(...));

        if (! $response->successful()) {
            return null;
        }

        $firstLine = (int) $reference->firstLine;
        $lastLine = min((int) $reference->lastLine, $firstLine + self::MAX_LINES - 1);

        $body = $response->toPsrResponse()->getBody();
        $text = '';

        while (! $body->eof() && mb_substr_count($text, "\n") < $lastLine && mb_strlen($text) < self::MAX_FILE_CHARACTERS) {
            $text .= $body->read(8192);
        }

        $body->close();

        $lines = array_slice(preg_split('/\R/', $text) ?: [], $firstLine - 1, $lastLine - $firstLine + 1);

        if ($lines === []) {
            return null;
        }

        return $this->metadata(
            $reference,
            title: Str::limit($reference->lines(), self::TITLE_LENGTH - 1, '…'),
            language: $reference->language(),
            extras: [
                'lines' => array_map(fn (string $line): string => Str::limit(mb_rtrim($line), self::MAX_LINE_LENGTH, '…'), $lines),
                'start' => $firstLine,
            ],
        );
    }

    /**
     * The author's GitHub username, and whether they are on devnull too, matched on their immutable GitHub ID. Bots,
     * like the one that publishes releases, are left out.
     *
     * @param  Fluent<string, mixed>  $data
     * @return array{author?: string, author_on_devnull?: bool}
     */
    private function author(Fluent $data, string $key): array
    {
        $login = $this->firstLine($data, "{$key}.login");

        if ($login === null || $data->string("{$key}.type")->value() === 'Bot') {
            return [];
        }

        return [
            'author' => $login,
            'author_on_devnull' => User::query()->where('github_id', $data->string("{$key}.id")->value())->exists(),
        ];
    }

    /**
     * @param  Extras  $extras
     * @return Metadata
     */
    private function metadata(
        GitHubReference $reference,
        ?string $title,
        ?GitHubReferenceType $type = null,
        ?string $state = null,
        ?int $stars = null,
        ?string $language = null,
        array $extras = [],
    ): array {
        return [
            'title' => $title,
            'description' => null,
            'image_url' => null,
            'site_name' => 'GitHub',
            'github' => [
                'type' => ($type ?? $reference->type)->value,
                'label' => $reference->label(),
                'state' => $state,
                'stars' => $stars,
                'language' => $language,
                ...$extras,
            ],
        ];
    }

    /**
     * Returns the first line of a text field, truncated to fit the title column, or null when it is blank.
     *
     * @param  Fluent<string, mixed>  $data
     */
    private function firstLine(Fluent $data, string $key): ?string
    {
        $line = $data->string($key)->before("\n")->squish()->limit(self::TITLE_LENGTH - 1, '…')->value();

        return $line === '' ? null : $line;
    }
}
