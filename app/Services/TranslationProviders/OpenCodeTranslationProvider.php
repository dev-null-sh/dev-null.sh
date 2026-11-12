<?php

declare(strict_types=1);

namespace App\Services\TranslationProviders;

use App\Ai\Agents\CommitTranslator;
use App\Contracts\Services\TranslationProvider;
use App\Exceptions\InvalidTranslation;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Translation from TranslationProvider
 */
final readonly class OpenCodeTranslationProvider implements TranslationProvider
{
    private const int MINIMUM_RETRY_SECONDS = 10;

    public function translate(string $text, ?string $language): array
    {
        $deadline = now()->getTimestamp() + CommitTranslator::TIMEOUT_SECONDS;

        try {
            return $this->requestTranslation($text, $language, CommitTranslator::TIMEOUT_SECONDS);
        } catch (InvalidTranslation $invalidTranslation) {
            $remainingSeconds = $deadline - now()->getTimestamp();

            throw_if($remainingSeconds < self::MINIMUM_RETRY_SECONDS, $invalidTranslation);

            return $this->requestTranslation($text, $language, $remainingSeconds);
        }
    }

    /**
     * @return Translation
     */
    private function requestTranslation(string $text, ?string $language, int $timeoutSeconds): array
    {
        $response = new CommitTranslator($language)->prompt($text, timeout: $timeoutSeconds);

        $source = $response['source_language'] ?? null;
        $body = $response['body'] ?? null;

        throw_if($response->structured === [], InvalidTranslation::class, 'empty_or_malformed_response');

        throw_if(! is_string($source) || ! is_string($body), InvalidTranslation::class, 'invalid_response_shape');

        return ['source_language' => Str::of($source)->trim()->lower()->before('-')->before('_')->value(), 'body' => $body];
    }
}
