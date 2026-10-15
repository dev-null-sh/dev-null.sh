<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\ModerationRuleMode;

final readonly class ModerationRun
{
    /**
     * @param  list<RuleResult>  $results
     */
    public function __construct(
        public string $model,
        public ?string $requestId,
        public int $inputTokens,
        public int $latencyMs,
        public array $results,
    ) {
        //
    }

    /**
     * Rules in Test mode are judged and recorded, but never act on a commit.
     *
     * @return list<RuleResult>
     */
    public function liveResults(): array
    {
        return array_values(array_filter($this->results, fn (RuleResult $result): bool => $result->mode === ModerationRuleMode::Live));
    }
}
