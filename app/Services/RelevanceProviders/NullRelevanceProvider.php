<?php

declare(strict_types=1);

namespace App\Services\RelevanceProviders;

use App\Contracts\Services\RelevanceProvider;

/**
 * Keeps every result, so developing and testing never reach a third party.
 */
final readonly class NullRelevanceProvider implements RelevanceProvider
{
    public function decideRelevance(string $query, array $bodies, bool $related = false): array
    {
        return array_fill(0, count($bodies), true);
    }
}
