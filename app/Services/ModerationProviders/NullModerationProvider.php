<?php

declare(strict_types=1);

namespace App\Services\ModerationProviders;

use App\Contracts\Services\ModerationProvider;

/**
 * Lets everything through, so developing and testing never reach a third party.
 */
final readonly class NullModerationProvider implements ModerationProvider
{
    public function moderate(string $text, array $imageDataUrls = []): array
    {
        return [];
    }

    public function isHarmful(string $text): bool
    {
        return false;
    }
}
