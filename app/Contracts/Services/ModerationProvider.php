<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Reviews what people post before it is published.
 */
interface ModerationProvider
{
    /**
     * The categories the content is flagged for, if any, each with its highest score.
     *
     * @param  list<string>  $imageDataUrls
     * @return array<string, float>
     */
    public function moderate(string $text, array $imageDataUrls = []): array;

    /**
     * Whether the text is harmful, including threats that are implied rather than spelled out. Anything but a clear no
     * counts as a yes.
     */
    public function isHarmful(string $text): bool;
}
