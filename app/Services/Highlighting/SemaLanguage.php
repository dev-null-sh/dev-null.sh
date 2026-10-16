<?php

declare(strict_types=1);

namespace App\Services\Highlighting;

use App\Enums\SemaPattern;
use Tempest\Highlight\Language;

/**
 * Sema, a Scheme-like Lisp with LLM primitives: https://sema-lang.com. It implements the contract rather than extending
 * tempest's base language, whose `{+`, `{-` and `{:class:` markup would read Sema maps as highlighter markup.
 */
final readonly class SemaLanguage implements Language
{
    public function getName(): string
    {
        return 'sema';
    }

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        return [];
    }

    public function getInjections(): array
    {
        return [];
    }

    public function getPatterns(): array
    {
        return SemaPattern::cases();
    }
}
