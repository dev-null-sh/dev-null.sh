<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Tom's post about a change people will notice that just landed on production, in the same persona as his replies.
 */
#[Provider('opencode')]
#[Model('deepseek-v4.1-flash')]
#[MaxTokens(600)]
#[Timeout(60)]
final class ChangelogWriter implements Agent, HasProviderOptions
{
    use Promptable;

    public function instructions(): string
    {
        return mb_trim(view('ai.agents.changelog-writer', ['botMention' => Tom::mention()])->render());
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        return ['thinking' => ['type' => 'disabled']];
    }
}
