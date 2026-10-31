<?php

declare(strict_types=1);

use App\Ai\Agents\CommitHeadlineWriter;
use App\Services\HeadlineProviders\NullHeadlineProvider;
use App\Services\HeadlineProviders\OpenAIHeadlineProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

beforeEach(function (): void {
    config(['ai.providers.opencode.key' => 'test-key', 'ai.providers.openai.key' => 'test-openai']);
});

it('gates all further processing at sixty percent suitability', function (float $probability, bool $accepted): void {
    Classification::fake([['post_3' => new BooleanAnswer($probability), 'post_8' => new BooleanAnswer(0)]])->preventStrayClassifications();

    expect(new OpenAIHeadlineProvider()->candidates([3 => 'What database are you using for vector search?', 8 => 'Sunny day']))->toBe($accepted ? [3] : []);
    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => count($prompt) === 2 && $prompt->asks('post_3') && $prompt->asks('post_8'));
})->with([
    'unsuitable' => [0.0, false],
    'below threshold' => [0.599, false],
    'at threshold' => [0.6, true],
    'between new and previous thresholds' => [0.65, true],
]);

it('returns all known matching headlines without generating one', function (): void {
    Classification::fake([
        [
            'post_4_headline_1' => new BooleanAnswer(0.699),
            'post_4_headline_2' => new BooleanAnswer(0.97),
            'post_4_headline_0' => new BooleanAnswer(0.7),
            'post_9_headline_0' => new BooleanAnswer(0),
            'post_9_headline_1' => new BooleanAnswer(0),
            'post_9_headline_2' => new BooleanAnswer(0),
        ],
    ])->preventStrayClassifications();
    CommitHeadlineWriter::fake()->preventStrayPrompts();

    expect(new OpenAIHeadlineProvider()->matches([4 => 'Trying pgvector for semantic search.', 9 => 'New Laravel features'], ['Vector search databases', 'Sunny weather', 'Postgres extensions']))
        ->toBe([4 => ['Vector search databases', 'Postgres extensions']]);

    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => count($prompt) === 6 && $prompt->asks('post_9_headline_2'));
    CommitHeadlineWriter::assertNeverPrompted();
});

it('does not interpret invalid or refused matching answers as no match', function (array $answer): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [$answer]])]);

    expect(fn (): array => new OpenAIHeadlineProvider()->matches(['Trying pgvector.'], ['Vector databases']))
        ->toThrow(RuntimeException::class, 'Invalid headline classification response.');

    Http::assertSentCount(1);
})->with([
    'invalid probability' => [['name' => 'post_0_headline_0', 'type' => 'predicate', 'probability' => 1.1]],
    'negative probability' => [['name' => 'post_0_headline_0', 'type' => 'predicate', 'probability' => -0.1]],
    'refused' => [['name' => 'post_0_headline_0', 'type' => 'refusal']],
    'missing known answer' => [['name' => 'unknown', 'type' => 'predicate', 'probability' => 1]],
]);

it('generates through DeepSeek when no existing headline matches', function (array $existing): void {
    Classification::fake([
        ['post_2_headline_0' => new BooleanAnswer(0), 'post_7_headline_0' => new BooleanAnswer(0)],
    ])->preventStrayClassifications();
    CommitHeadlineWriter::fake([['post_7' => 'Laravel AI SDK features', 'post_2' => '  Vector search databases  ']])->preventStrayPrompts();
    $provider = new OpenAIHeadlineProvider();
    $bodies = [2 => 'What database are you using for vector search?', 7 => 'New Laravel AI SDK features'];

    expect($provider->matches($bodies, $existing))->toBe([])
        ->and($provider->generate($bodies))->toBe([2 => 'Vector search databases', 7 => 'Laravel AI SDK features']);

    CommitHeadlineWriter::assertPrompted(fn (AgentPrompt $prompt): bool => json_decode($prompt->prompt, true)['posts'] === ['post_2' => $bodies[2], 'post_7' => $bodies[7]]
        && $prompt->model === 'deepseek-v4.1-flash'
        && $prompt->provider->name() === 'opencode');
})->with([
    'no existing headlines' => [[]],
    'no matching headlines' => [['Laravel releases']],
]);

it('propagates failures rather than treating them as a negative decision', function (string $stage): void {
    Http::fake([
        'api.openai.com/v1/decisions' => Http::sequence()
            ->push('sensitive response', 500),
        'opencode.ai/zen/v1/chat/completions' => Http::response('sensitive response', 500),
    ]);

    $provider = new OpenAIHeadlineProvider();

    expect(fn (): array => match ($stage) {
        'gate' => $provider->candidates(['Trying pgvector.']),
        'matching' => $provider->matches(['Trying pgvector.'], ['Vector databases']),
        'generation' => $provider->generate(['Trying pgvector.']),
    })->toThrow(RequestException::class);
})->with(['gate', 'matching', 'generation']);

it('does not process headlines locally', function (): void {
    $provider = new NullHeadlineProvider();

    expect($provider->candidates(['Trying pgvector.']))->toBe([])
        ->and($provider->matches(['Trying pgvector.'], ['Vector databases']))->toBe([])
        ->and($provider->generate(['Trying pgvector.']))->toBe([])
        ->and($provider->deduplicate(['Vector databases']))->toBe([]);

    Http::assertNothingSent();
});

it('rejects unusable generated headlines without exposing the response', function (string $content): void {
    Http::fake([
        'opencode.ai/zen/v1/chat/completions' => Http::response([
            'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $content]]],
        ]),
    ]);

    expect(fn (): array => new OpenAIHeadlineProvider()->generate(['Trying pgvector.']))->toThrow(RuntimeException::class, 'Invalid headline response.');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://opencode.ai/zen/v1/chat/completions'
        && $request['thinking'] === ['type' => 'disabled']
        && $request['response_format'] === ['type' => 'json_object']);
})->with([
    'malformed' => ['not JSON'],
    'missing headline' => ['{}'],
    'wrong type' => ['{"post_0":42}'],
    'blank' => ['{"post_0":"  "}'],
    'too long' => [json_encode(['post_0' => str_repeat('a', 81)], JSON_THROW_ON_ERROR)],
]);

it('keeps the first equivalent headline without merging through a discarded intermediate', function (): void {
    Classification::fake([[
        'pair_8_2' => new BooleanAnswer(0.7),
        'pair_8_5' => new BooleanAnswer(0.699),
        'pair_2_5' => new BooleanAnswer(0.99),
        'pair_8_9' => new BooleanAnswer(0),
        'pair_2_9' => new BooleanAnswer(0),
        'pair_5_9' => new BooleanAnswer(0),
    ]])->preventStrayClassifications();

    expect(new OpenAIHeadlineProvider()->deduplicate([
        8 => 'Vector search databases', 2 => 'Databases for vector search', 5 => 'Postgres extensions', 9 => 'Vector search databases',
    ]))->toBe([8 => 'Vector search databases', 2 => 'Vector search databases', 5 => 'Postgres extensions', 9 => 'Vector search databases']);
});

it('splits matching questions at the API limit without losing results', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => function (Request $request) {
        expect(count($request['questions']))->toBeLessThanOrEqual(400);

        return Http::response(['answers' => collect($request['questions'])->map(fn (array $question): array => [
            'name' => $question['name'], 'type' => 'predicate', 'probability' => in_array($question['name'], ['post_1_headline_199', 'post_1_headline_200'], true) ? 1 : 0,
        ])->all()]);
    }]);

    $names = array_map(fn (int $index): string => 'Headline '.$index, range(0, 200));

    expect(new OpenAIHeadlineProvider()->matches(['Rust', 'Postgres'], $names))->toBe([1 => ['Headline 199', 'Headline 200']]);
    Http::assertSentCount(2);
});

it('makes no requests for empty stages or deduplicating a single headline', function (): void {
    $provider = new OpenAIHeadlineProvider();

    expect($provider->candidates([]))->toBe([])
        ->and($provider->matches([], ['Vector search']))->toBe([])
        ->and($provider->generate([]))->toBe([])
        ->and($provider->deduplicate([]))->toBe([])
        ->and($provider->deduplicate([4 => 'Vector search']))->toBe([4 => 'Vector search']);
    Http::assertNothingSent();
});

it('gives each decision only its own post rather than sharing other posts as evidence', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => function (Request $request) {
        expect($request['input'])->not->toContain('Trying pgvector', 'New Laravel features');
        expect($request['questions'][0]['instructions'])->toContain('Trying pgvector')->not->toContain('New Laravel features');
        expect($request['questions'][1]['instructions'])->toContain('New Laravel features')->not->toContain('Trying pgvector');

        return Http::response(['answers' => collect($request['questions'])->map(fn (array $question): array => [
            'name' => $question['name'], 'type' => 'predicate', 'probability' => 0,
        ])->all()]);
    }]);
    $provider = new OpenAIHeadlineProvider();

    expect($provider->candidates(['Trying pgvector', 'New Laravel features']))->toBe([])
        ->and($provider->matches(['Trying pgvector', 'New Laravel features'], ['Vector databases']))->toBe([]);
    Http::assertSentCount(2);
});
