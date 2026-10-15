<?php

declare(strict_types=1);

use App\Ai\Agents\TopicExtractor;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Enums\Lab;

it('asks for a list of topics', function (): void {
    $schema = new TopicExtractor()->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['topics'])
        ->and(new TopicExtractor()->instructions())->toContain('Return up to 3 topics');
});

it('runs at low effort on Anthropic only', function (): void {
    $agent = new TopicExtractor;

    expect($agent->providerOptions(Lab::Anthropic))->toBe(['output_config' => ['effort' => 'low']])
        ->and($agent->providerOptions('anthropic'))->toBe(['output_config' => ['effort' => 'low']])
        ->and($agent->providerOptions(Lab::OpenAI))->toBe([]);
});
