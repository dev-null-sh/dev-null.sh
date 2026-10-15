<?php

declare(strict_types=1);

namespace App\Actions;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use RuntimeException;

final readonly class ExtractCommitTopics
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
        You file commits, the short updates developers share on a social network, under topics.
        Each one has a title and sometimes a description. Return up to 3 topics that say what it is about: technologies, languages, tools or subjects,
        such as "laravel", "rust", "hiring" or "open-source". Prefer the general term other posts would share
        over a phrase only this commit would use. Each topic is lowercase and singular, one word or a few joined by hyphens.
        Return no topics when it has no clear subject, like a greeting.
        PROMPT;

    public function __construct(private Client $client)
    {
        //
    }

    /**
     * @return list<string>
     */
    public function handle(string $text): array
    {
        $message = $this->client->beta->messages->create(
            maxTokens: 2048,
            messages: [['role' => 'user', 'content' => $text]],
            model: config()->string('services.anthropic.model'),
            fallbacks: 'default',
            outputConfig: [
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['topics'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            system: self::SYSTEM_PROMPT,
            betas: ['server-side-fallback-2026-07-01'],
        );

        throw_if($message->stopReason === 'refusal', RuntimeException::class, 'Claude declined to extract topics.');

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                return $this->normalize($block->text);
            }
        }

        throw new RuntimeException('Claude returned no topics.');
    }

    /**
     * @return list<string>
     */
    private function normalize(string $json): array
    {
        $output = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        throw_unless(is_array($output) && is_array($output['topics'] ?? null), RuntimeException::class, 'Claude returned malformed topics.');

        $names = array_map(
            fn (string $topic): string => mb_substr(mb_strtolower(mb_trim($topic)), 0, 50),
            array_filter($output['topics'], is_string(...)),
        );

        return array_slice(array_values(array_unique(array_filter($names))), 0, 3);
    }
}
