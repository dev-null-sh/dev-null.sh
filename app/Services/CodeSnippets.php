<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\GitHubPermalink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tempest\Highlight\Highlighter;

/**
 * The lines a GitHub permalink points at, shown in place of the link's card. A commit SHA never changes, so the lines
 * are cached as fetched; they are kept for a while rather than stored, and the link falls back to its card after that.
 *
 * shortcut: an expired snippet is not fetched again, upgrade to re-queueing the unfurl if old cards should keep it.
 */
final readonly class CodeSnippets
{
    public const int MAX_LINES = 30;

    private const int MAX_LINE_LENGTH = 300;

    private const int CACHED_DAYS = 30;

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * Keeps the cited lines of the file, up to the first 30, cutting very long ones short. Nothing is kept when the
     * file has no such lines or isn't text. Only the cited lines are checked, since a big file arrives cut off, maybe
     * in the middle of a character.
     */
    public function put(GitHubPermalink $permalink, string $file): void
    {
        $lines = array_slice(
            preg_split('/\R/', $file) ?: [],
            $permalink->startLine - 1,
            min($permalink->endLine - $permalink->startLine + 1, self::MAX_LINES),
        );
        $text = implode("\n", $lines);

        if ($lines === [] || ! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
            return;
        }

        Cache::put($permalink->cacheKey(), array_map(fn (string $line): string => Str::limit($line, self::MAX_LINE_LENGTH, '…'), $lines), now()->addDays(self::CACHED_DAYS));
    }

    /**
     * The highlighted lines, each led by its line number in the file. The highlighter escapes the code, so the HTML is
     * safe to render as is.
     *
     * The cited range is returned too, so a range longer than what is shown can say so.
     *
     * @return array{path: string, start_line: int, end_line: int, cited_end_line: int, html: string}|null
     */
    public function for(string $url): ?array
    {
        $permalink = GitHubPermalink::parse($url);
        $lines = $permalink instanceof GitHubPermalink ? Cache::memo()->get($permalink->cacheKey()) : null;

        if (! is_array($lines)) {
            return null;
        }

        /** @var list<string> $lines */
        $html = explode("\n", $this->highlighter->parse(implode("\n", $lines), mb_strtolower(pathinfo($permalink->path, PATHINFO_EXTENSION))));

        return [
            'path' => $permalink->path,
            'start_line' => $permalink->startLine,
            'end_line' => $permalink->startLine + count($lines) - 1,
            'cited_end_line' => $permalink->endLine,
            'html' => implode("\n", array_map(
                fn (string $line, int $index): string => sprintf('<span class="hl-gutter">%d</span>%s', $permalink->startLine + $index, $line),
                $html,
                array_keys($html),
            )),
        ];
    }
}
