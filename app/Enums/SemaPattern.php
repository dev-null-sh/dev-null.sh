<?php

declare(strict_types=1);

namespace App\Enums;

use Tempest\Highlight\IsPattern;
use Tempest\Highlight\Pattern;
use Tempest\Highlight\Tokens\TokenTypeEnum;

/**
 * The tokens of Sema code, one case each, following the reader (crates/sema-reader) and the official grammars. The
 * highlighter matches every case against the whole code block, and where two matches overlap, the one that starts
 * first wins, so a `;` inside a string never starts a comment. Of two matches of the same text, the earlier case wins.
 */
enum SemaPattern implements Pattern
{
    use IsPattern;

    case Comment;
    case String;
    case Character;
    case DefinedName;
    case SpecialForm;
    case Keyword;
    case Literal;
    case Number;
    case NamespacedCall;
    case LambdaArgument;

    /**
     * The evaluator's special forms (crates/sema-eval/src/special_forms.rs) and the prelude's core macros, with the
     * clause words and macros every official grammar highlights alongside them.
     */
    private const array SPECIAL_FORMS = [
        ...self::DEFINITION_FORMS,
        'lambda', 'fn', 'let', 'let*', 'letrec', 'let-values', 'let*-values', 'if', 'if-let', 'when', 'when-let',
        'unless', 'cond', 'case', 'match', 'match*', 'else', 'begin', 'do', 'progn', 'and', 'or', 'set!', 'quote',
        'quasiquote', 'unquote', 'unquote-splicing', 'try', 'catch', 'throw', 'import', 'export', 'module', 'load',
        'eval', 'macroexpand', 'while', 'dotimes', 'for-range', 'guard', 'parameterize', 'delay', 'force', 'async',
        'await', 'prompt', 'message', 'with-retry', 'with-session', 'with-span', 'with-stream', 'with-open',
        'llm/with-budget', 'policy/without', 'io/with-raw-mode', 'term/with-alt-screen', 'term/with-mouse',
        'term/with-bracketed-paste', 'term/with-focus-events', 'term/with-kitty-keys', '->', '->>', 'as->', 'some->',
    ];

    /**
     * The forms whose first argument is the name they define: `(defagent guide ...)` or `(define (greet name) ...)`.
     */
    private const array DEFINITION_FORMS = [
        'define', 'def', 'defn', 'defun', 'defmacro', 'define-syntax', 'define-values', 'define-record-type',
        'defagent', 'deftool', 'defworkflow', 'defpolicy', 'defmulti', 'defmethod',
    ];

    /**
     * A character of a symbol: a letter, a digit, or one of the punctuation the reader allows, as in `string/split`.
     */
    private const string SYMBOL = '[\p{L}\p{N}_+\-*\/!?<>=&%^~.#]';

    /**
     * A string: a plain one, a regex literal, or an f-string, whose `${...}` may hold strings and maps of its own.
     */
    private const string STRING = '"(?:[^"\\\\]|\\\\.)*"|#"(?:[^"\\\\]|\\\\.)*"'
        .'|(?<![\p{L}\p{N}_])f"(?:[^"\\\\$]|\\\\.|\$(?!\{)|\$(?<interpolation>\{(?:[^{}"\\\\]|\\\\.|"(?:[^"\\\\]|\\\\.)*"|(?&interpolation))*\}))*"';

    /**
     * An atom starts after whitespace, an opening bracket, a quote, or `@`...
     */
    private const string ATOM_START = '(?<![^\s(\[{\'`,@])';

    /**
     * ...and ends before whitespace, a bracket, a string, a comment, or a quote.
     */
    private const string ATOM_END = '(?![^\s()\[\]{}";\'`,])';

    public function getPattern(): string
    {
        $pattern = match ($this) {
            // Strings, comments, and characters each skip over the other two, as the reader does, so a `;` in a string
            // never starts a comment, a `"` in a comment never starts a string, and `#\"` is just a character...
            self::Comment => '(?:'.self::STRING.'|#\\\\.)(*SKIP)(*FAIL)|(?<match>;.*|\A#!.*)',
            self::String => '(?:;.*|#\\\\.)(*SKIP)(*FAIL)|(?<match>'.self::STRING.')',
            self::Character => self::atom('#\\\\(?:space|newline|tab|return|nul|.)'),
            self::DefinedName => '\((?:'.self::alternatives(self::DEFINITION_FORMS).')\s+\(?(?<match>'.self::SYMBOL.'+)',
            self::SpecialForm => '(?<![^\s(\[{])(?<match>(?:'.self::alternatives(self::SPECIAL_FORMS).'))'.self::ATOM_END,
            self::Keyword => '(?<![^\s(\[{\'`,@\p{L}\p{N}_+\-*\/!?<>=&%^~.#])(?<match>:'.self::SYMBOL.'+)',
            self::Literal => self::atom('#true|#false|#t|#f|true|false|nil'),
            self::Number => self::atom(self::number()),
            self::NamespacedCall => '(?<=\()(?<!define \()(?<!def \()(?<match>[\p{L}\p{N}_-]+\/'.self::SYMBOL.'+)',
            self::LambdaArgument => self::atom('%&|%\d*'),
        };

        return "/{$pattern}/u";
    }

    public function getTokenType(): TokenTypeEnum
    {
        return match ($this) {
            self::Comment => TokenTypeEnum::COMMENT,
            self::String, self::Character => TokenTypeEnum::VALUE,
            self::DefinedName => TokenTypeEnum::TYPE,
            self::SpecialForm => TokenTypeEnum::KEYWORD,
            self::Keyword => TokenTypeEnum::PROPERTY,
            self::Literal => TokenTypeEnum::LITERAL,
            self::Number => TokenTypeEnum::NUMBER,
            self::NamespacedCall, self::LambdaArgument => TokenTypeEnum::VARIABLE,
        };
    }

    /**
     * @param  list<string>  $words
     */
    private static function alternatives(array $words): string
    {
        return implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), $words));
    }

    /**
     * Matches the given alternatives as a whole atom only, so `if` never matches inside `if-let` or `diff`.
     */
    private static function atom(string $alternatives): string
    {
        return self::ATOM_START.'(?<match>(?:'.$alternatives.'))'.self::ATOM_END;
    }

    /**
     * Integers, decimals, rationals, and complex numbers, with their `#e`, `#i`, and `#d` prefixes; signed integers in
     * `#x`, `#o`, and `#b`; and the spellings of infinity and NaN.
     */
    private static function number(): string
    {
        $real = '\d+(?:\/\d+|(?:\.\d+)?(?:[eE][+-]?\d+)?)';
        $decimal = "(?:#[eEiIdD]){0,2}(?:[+-]?{$real}(?:[+-](?:{$real})?i)?|[+-]?{$real}i|[+-]i)";
        $radix = '(?:#[eEiI])?(?:#[xX](?:#[eEiI])?[+-]?[\da-fA-F]+|#[oO](?:#[eEiI])?[+-]?[0-7]+|#[bB](?:#[eEiI])?[+-]?[01]+)';

        return "{$radix}|{$decimal}|[+-]?(?:inf|Inf|Infinity)|-inf|nan|NaN|NAN|[+-]nan";
    }
}
