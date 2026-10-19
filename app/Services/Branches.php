<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Branches
{
    /**
     * A commit carries at most one branch name, stored in `commits.branch`.
     */
    public const int MAX_PER_COMMIT = 1;

    /**
     * Stored branch names: 1–50 lowercase letters, digits, underscores or hyphens, with at least one letter. Keep in
     * sync with resources/js/lib/branches.ts and the `#branch` linkifier in resources/js/components/linkified-text.tsx.
     */
    public const string NAME_PATTERN = '/^(?=[a-z0-9][a-z0-9_-]{0,49}$)(?=.*[a-z])[a-z0-9_-]+$/';

    /**
     * Legacy "#branch" in prose (for linkifying old bodies). Keep in sync with linkified-text.tsx.
     */
    private const string INLINE_PATTERN = '/(?<![\p{L}\p{N}_#\/-])#([A-Za-z0-9][A-Za-z0-9_-]{0,49})(?![A-Za-z0-9_-])/u';

    public function __construct(private CodeSpans $codeSpans)
    {
        //
    }

    /**
     * Normalizes a composer branch field: trim, strip a leading "#", lowercase. Empty becomes null.
     */
    public function normalize(?string $branch): ?string
    {
        if ($branch === null) {
            return null;
        }

        $branch = mb_strtolower(mb_ltrim(mb_trim($branch), '#'));

        return $branch === '' ? null : $branch;
    }

    /**
     * Finds legacy "#branch" tokens in text (backfill / linkify helpers). At most {@see MAX_PER_COMMIT} are kept.
     *
     * @return list<string>
     */
    public function namesInBody(string $text): array
    {
        preg_match_all(self::INLINE_PATTERN, $this->codeSpans->withoutCode($text), $matches);

        $names = [];

        foreach ($matches[1] as $name) {
            if (! preg_match('/[A-Za-z]/', $name)) {
                continue;
            }

            $names[mb_strtolower($name)] ??= mb_strtolower($name);

            if (count($names) >= self::MAX_PER_COMMIT) {
                break;
            }
        }

        return array_values($names);
    }

    /**
     * Whether the string is a single stored branch name (no leading "#").
     */
    public function isValid(string $branch): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $branch);
    }
}
