<?php

declare(strict_types=1);

namespace App\Services\TranslationProviders;

use App\Contracts\Services\TranslationProvider;

/**
 * Can't tell any language, so nothing is ever translated while developing and testing.
 */
final readonly class NullTranslationProvider implements TranslationProvider
{
    public function translate(string $text, ?string $language): array
    {
        return ['source_language' => 'und', 'body' => $text];
    }
}
