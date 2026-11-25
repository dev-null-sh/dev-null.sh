<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Whether a body is nothing but a few emoji, so it shows them large: the rule resources/js/lib/emoji.ts applies on the
 * web, sent to API clients so every surface agrees. tests/Fixtures/jumbo-emoji.json holds the cases both must pass.
 */
final readonly class JumboEmoji
{
    /**
     * The most emoji a body shows large; more show at their normal size.
     */
    public const int MAX = 3;

    /**
     * Longer than any body of only emoji worth telling apart, counted in UTF-16 code units as the web counts it.
     */
    private const int MAX_LENGTH = 128;

    /**
     * One emoji as it shows by default (🔥), a text symbol asked to show as one (❤️), or either with a skin tone (👋🏽).
     * A lone regional indicator is half a flag, never an emoji of its own.
     */
    private const string ELEMENT = '(?:(?![\x{1F1E6}-\x{1F1FF}])\p{Emoji_Presentation}\p{Emoji_Modifier}?|\p{Extended_Pictographic}\x{FE0F}|\p{Emoji_Modifier_Base}\p{Emoji_Modifier})';

    /**
     * One whole emoji: a flag (🇵🇹), a keycap (1️⃣), a subdivision flag (🏴󠁧󠁢󠁳󠁣󠁴󠁿), or emoji joined by ZWJ (👩‍💻).
     * Bare `1`, `#` or `©` aren't.
     */
    private const string SINGLE_EMOJI = '/^(?:[\x{1F1E6}-\x{1F1FF}]{2}|[0-9#*]\x{FE0F}\x{20E3}|\x{1F3F4}[\x{E0061}-\x{E007A}]+\x{E007F}|'.self::ELEMENT.'(?:\x{200D}'.self::ELEMENT.')*)$/u';

    public function __construct(private EmojiShortcodes $shortcodes)
    {
        //
    }

    /**
     * How many emoji a body shows large: one to three when it is nothing but them, 0 otherwise.
     */
    public function count(string $body): int
    {
        $count = $this->emojiOnlyCount($body);

        return $count <= self::MAX ? $count : 0;
    }

    /**
     * How many emoji a body is made of when it is nothing but emoji, with shortcodes counted as theirs; 0 otherwise.
     */
    public function emojiOnlyCount(string $body): int
    {
        if (
            // UTF-16 counts a character past U+FFFF, as most emoji are, twice.
            mb_strlen($body) + preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $body) > self::MAX_LENGTH
            || preg_match('/[a-z]/i', (string) preg_replace('/:[a-z0-9_+-]+:/i', '', $body)) === 1
        ) {
            return 0;
        }

        // \X takes one grapheme at a time, the way the web's Intl.Segmenter splits text: a whole flag or ZWJ family each.
        preg_match_all('/\X/u', $this->shortcodes->replace($body), $graphemes);

        $count = 0;

        foreach ($graphemes[0] as $grapheme) {
            if (preg_match('/^[\s\p{Z}\x{FEFF}]+$/u', $grapheme) === 1) {
                continue;
            }

            if (preg_match(self::SINGLE_EMOJI, $grapheme) !== 1) {
                return 0;
            }

            $count++;
        }

        return $count;
    }
}
