<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Detects the language a post is written in and translates it.
 *
 * @phpstan-type Translation array{source_language: string, body: string}
 */
interface TranslationProvider
{
    /**
     * The post's language, as an ISO 639-1 code when there is one, or "und" when it can't be told, and the post
     * translated into the language, or left as it is without one. Throws an App\Exceptions\InvalidTranslation when
     * the answer isn't one.
     *
     * @return Translation
     */
    public function translate(string $text, ?string $language): array;
}
