<?php

declare(strict_types=1);

namespace App\Services\TranslationProviders;

use App\Ai\Agents\CommitTranslator;
use App\Contracts\Services\TranslationProvider;
use App\Exceptions\InvalidTranslation;

final readonly class OpenCodeTranslationProvider implements TranslationProvider
{
    public function translate(string $text, ?string $language): array
    {
        $response = new CommitTranslator($language)->prompt($text);

        $source = $response['source_language'] ?? null;
        $body = $response['body'] ?? null;

        throw_if($response->structured === [], InvalidTranslation::class, 'empty_or_malformed_response');

        throw_if(! is_string($source) || ! is_string($body), InvalidTranslation::class, 'invalid_response_shape');

        return ['source_language' => $source, 'body' => $body];
    }
}
