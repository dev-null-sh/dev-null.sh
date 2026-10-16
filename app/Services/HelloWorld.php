<?php

declare(strict_types=1);

namespace App\Services;

final readonly class HelloWorld
{
    /**
     * The whole commit is a hello world, whatever the case, with an optional comma and
     * trailing punctuation. Anything else in the text is a different commit.
     */
    public function matches(string $body): bool
    {
        $normalized = mb_strtolower(mb_trim($body, " \t\n\r\0\x0B\"'`"));
        $normalized = preg_replace('/[.!?…]+$/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', mb_trim($normalized)) ?? $normalized;

        return in_array($normalized, ['hello world', 'hello, world'], true);
    }
}
