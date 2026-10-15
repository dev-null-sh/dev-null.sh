<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-opus-5-5')]
#[MaxTokens(2048)]
final class TopicExtractor implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
            You file commits, the short updates developers share on a social network, under topics.
            Each one has a title and sometimes a description. Return up to 3 topics that say what it is about: technologies, languages, tools or subjects,
            such as "laravel", "rust", "hiring" or "open-source". Prefer the general term other commits would share
            over a phrase only this commit would use. Each topic is lowercase and singular, one word or a few joined by hyphens.
            Return no topics when it has no clear subject, like a greeting.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topics' => $schema->array()->items($schema->string())->required(),
        ];
    }

    /**
     * Picking a few words is simple, so low effort keeps each call cheap and quick.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::Anthropic, Lab::Anthropic->value => ['output_config' => ['effort' => 'low']],
            default => [],
        };
    }
}
