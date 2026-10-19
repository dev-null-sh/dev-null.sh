<?php

declare(strict_types=1);

namespace App\Services;

use Tempest\Highlight\Theme;
use Tempest\Highlight\Themes\CssTheme;
use Tempest\Highlight\Tokens\DynamicTokenType;
use Tempest\Highlight\Tokens\TokenType;

/**
 * Highlights code the way the default theme does, but never with a class name taken from the code itself:
 * the highlighter's `{:class:text:}` markup would otherwise let a commit hide part of a code block from
 * readers with a utility class like `hidden`, while the Copy button still copies it.
 */
final readonly class CodeTheme implements Theme
{
    public function __construct(private CssTheme $theme = new CssTheme())
    {
        //
    }

    public function before(TokenType $tokenType): string
    {
        if ($tokenType instanceof DynamicTokenType && preg_match('/^hl-[a-z]+(-[a-z]+)*$/', $tokenType->getValue()) !== 1) {
            return '<span>';
        }

        return $this->theme->before($tokenType);
    }

    public function after(TokenType $tokenType): string
    {
        return $this->theme->after($tokenType);
    }

    public function escape(string $content): string
    {
        return $this->theme->escape($content);
    }
}
