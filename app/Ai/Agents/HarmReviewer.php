<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Catches the threats the moderation model misses because they are implied rather than spelled out, such as claiming
 * to have a bomb.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6.1-sol')]
#[Timeout(60)]
final class HarmReviewer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
            You review posts on /dev/null, a social network for developers, before they are published.
            Answer whether the post is harmful: a threat, including claims of having a bomb, weapon or explosive, or of
            planning an attack, even as a joke; violence, incitement or glorification of it; hate speech or harassment of a
            person or group; sexual content; encouragement of self-harm; or illegal activity. Developer jargon such as
            "kill the process", "nuke the database" or "this release is the bomb" is not harmful. Disliking a technology
            is not harmful either. When unsure, answer true.
            TEXT;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'harmful' => $schema->boolean()->required(),
        ];
    }
}
