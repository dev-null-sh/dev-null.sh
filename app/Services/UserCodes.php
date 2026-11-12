<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The short code a terminal shows for the user to type on the web when it signs in: eight consonants in two groups,
 * the alphabet RFC 8628 suggests (section 6.1), which reads out loud without look-alikes and still leaves about
 * 34 bits for a code that only lives a few minutes.
 */
final readonly class UserCodes
{
    public const string Alphabet = 'BCDFGHJKLMNPQRSTVWXZ';

    private const int Length = 8;

    public function generate(): string
    {
        $letters = '';

        for ($position = 0; $position < self::Length; $position++) {
            $letters .= self::Alphabet[random_int(0, mb_strlen(self::Alphabet) - 1)];
        }

        return $this->normalize($letters);
    }

    /**
     * The code as the terminal shows it, out of what the user typed: uppercase, without spaces or dashes, and with
     * its dash back once it has the eight letters. Anything else is left as it is, for validation to refuse.
     */
    public function normalize(string $typed): string
    {
        $letters = mb_strtoupper((string) preg_replace('/[\s-]+/u', '', $typed));

        return mb_strlen($letters) === self::Length
            ? mb_substr($letters, 0, 4).'-'.mb_substr($letters, 4)
            : $letters;
    }
}
