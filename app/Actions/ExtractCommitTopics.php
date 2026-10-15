<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\TopicExtractor;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final readonly class ExtractCommitTopics
{
    /**
     * @return list<string>
     */
    public function handle(string $text): array
    {
        $response = new TopicExtractor()->prompt($text);

        throw_unless($response instanceof StructuredAgentResponse, RuntimeException::class, 'Claude returned no structured output.');

        $topics = $response->toArray()['topics'] ?? null;

        throw_unless(is_array($topics), RuntimeException::class, 'Claude returned malformed topics.');

        $names = array_map(
            fn (string $topic): string => mb_substr(mb_strtolower(mb_trim($topic)), 0, 50),
            array_filter($topics, is_string(...)),
        );

        return array_slice(array_values(array_unique(array_filter($names))), 0, 3);
    }
}
