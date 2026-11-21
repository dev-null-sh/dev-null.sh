<?php

declare(strict_types=1);

namespace App\Services\ToolDecisionProviders;

use App\Contracts\Services\ToolDecisionProvider;
use Illuminate\Support\Collection;

/**
 * Needs no tool for any message, so developing and testing never reach a third party to decide.
 */
final readonly class NullToolDecisionProvider implements ToolDecisionProvider
{
    public function tools(string $message, iterable $history): Collection
    {
        return collect();
    }
}
