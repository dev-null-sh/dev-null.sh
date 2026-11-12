<?php

declare(strict_types=1);

namespace App\Services;

final readonly class EncodedText
{
    private const array MORSE = [
        '.-' => 'A', '-...' => 'B', '-.-.' => 'C', '-..' => 'D', '.' => 'E', '..-.' => 'F',
        '--.' => 'G', '....' => 'H', '..' => 'I', '.---' => 'J', '-.-' => 'K', '.-..' => 'L',
        '--' => 'M', '-.' => 'N', '---' => 'O', '.--.' => 'P', '--.-' => 'Q', '.-.' => 'R',
        '...' => 'S', '-' => 'T', '..-' => 'U', '...-' => 'V', '.--' => 'W', '-..-' => 'X',
        '-.--' => 'Y', '--..' => 'Z',
        '-----' => '0', '.----' => '1', '..---' => '2', '...--' => '3', '....-' => '4',
        '.....' => '5', '-....' => '6', '--...' => '7', '---..' => '8', '----.' => '9',
        '.-.-.-' => '.', '--..--' => ',', '..--..' => '?', '.----.' => "'", '-.-.--' => '!',
        '-..-.' => '/', '-.--.' => '(', '-.--.-' => ')', '.-...' => '&', '---...' => ':',
        '-.-.-.' => ';', '-...-' => '=', '.-.-.' => '+', '-....-' => '-', '..--.-' => '_',
        '.-..-.' => '"', '...-..-' => '$', '.--.-.' => '@',
    ];

    /**
     * Only whole encoded posts: eight-bit UTF-8 bytes, or International Morse with spaces between letters and
     * slashes or three spaces between words. Mixed prose, code fences and ambiguous punctuation stay unchanged.
     *
     * @return array{source_language: string, body: string}|null
     */
    public function decode(string $text): ?array
    {
        $text = mb_trim($text);

        if (preg_match('/\A(?:[01]{8}\s*)+\z/', $text) === 1) {
            $bits = (string) preg_replace('/\s+/', '', $text);
            $body = pack('C*', ...array_map(fn (string $byte): int => (int) bindec($byte), mb_str_split($bits, 8)));

            if (preg_match('//u', $body) === 1 && preg_match('/[^\P{C}\t\r\n\x{200C}\x{200D}]/u', $body) === 0 && mb_trim($body) !== '') {
                return ['source_language' => 'binary', 'body' => $body];
            }

            return null;
        }

        $morse = strtr($text, ['·' => '.', '•' => '.', '∙' => '.', '⋅' => '.', '−' => '-', '–' => '-', '—' => '-']);

        if (preg_match('/\A[.\-\/\s]+\z/', $morse) !== 1) {
            return null;
        }

        $lines = [];
        $letters = 0;

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $morse)) as $line) {
            $words = [];

            foreach (preg_split('/(?:[ \t]*\/[ \t]*|[ \t]{3,})/', mb_trim($line)) ?: [] as $word) {
                $decoded = '';

                foreach (preg_split('/[ \t]+/', $word, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $letter) {
                    if (! isset(self::MORSE[$letter])) {
                        return null;
                    }

                    $decoded .= self::MORSE[$letter];
                    $letters++;
                }

                $words[] = $decoded;
            }

            $lines[] = implode(' ', $words);
        }

        $body = implode("\n", $lines);

        if ($letters < 2 || preg_match('/[A-Z0-9]/', $body) !== 1) {
            return null;
        }

        // Morse has no letter case; use sentence case rather than shouting or guessing proper names.
        $body = preg_replace_callback('/(?:\A|[.!?]\s+|\n)[^a-z]*\K[a-z]/', fn (array $match): string => mb_strtoupper($match[0]), mb_strtolower($body)) ?? $body;

        return ['source_language' => 'morse', 'body' => $body];
    }
}
