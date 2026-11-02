<?php

declare(strict_types=1);

use App\Services\ToolDecisionProviders\OpenAIToolDecisionProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Messages\Message;

beforeEach(function (): void {
    config(['ai.providers.openai.key' => 'test-openai']);
});

it('asks one question per tool under the shared policy, with the thread as context', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => collect(['SearchCommits', 'SearchKnowledgeBase', 'RepositoryInsights', 'SearchRepositoryHistory', 'CommunityStats'])
        ->map(fn (string $name): array => ['name' => $name, 'type' => 'predicate', 'probability' => 1])->all()])]);

    $tools = new OpenAIToolDecisionProvider()->tools('Tell me about badges, how they evolved and what people think of them.', [
        new Message('user', '@alice: How do bookmarks work?'),
        new Message('assistant', '@tom: They save posts for later.'),
    ]);

    expect($tools->map(class_basename(...))->all())->toBe(['SearchCommits', 'SearchKnowledgeBase', 'RepositoryInsights', 'SearchRepositoryHistory', 'CommunityStats']);
    Http::assertSent(function (Request $request): bool {
        $questions = collect($request['questions']);

        expect(json_encode($questions))->toContain('notable events', 'how it evolved', 'community reactions', 'particular calendar date')
            ->and($questions->every(fn (array $question): bool => str_contains(json_encode($question), 'Sources are complementary, not mutually exclusive')))->toBeTrue()
            ->and($request['input'])->toContain('Tell me about badges', '@alice: How do bookmarks work?', '@tom: They save posts for later.', '"role":"assistant"');

        return $request->url() === 'https://api.openai.com/v1/decisions'
            && $request['model'] === 'gpt-6-luna'
            && $questions->pluck('name')->all() === ['SearchCommits', 'SearchKnowledgeBase', 'RepositoryInsights', 'SearchRepositoryHistory', 'CommunityStats']
            && $questions->every(fn (array $question): bool => $question['type'] === 'predicate');
    });
});

it('selects only known affirmative predicates at or above the probability threshold', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['name' => 'CommunityStats', 'type' => 'predicate', 'probability' => 0.5],
        ['name' => 'SearchKnowledgeBase', 'type' => 'predicate', 'probability' => 0.49],
        ['name' => 'RepositoryInsights', 'type' => 'refusal', 'probability' => 1],
        ['name' => 'SearchCommits', 'type' => 'choice', 'choice' => 'yes'],
        ['name' => 'SearchRepositoryHistory', 'type' => 'predicate', 'probability' => 1.1],
        ['name' => 'UnknownTool', 'type' => 'predicate', 'probability' => 1],
        'invalid answer',
    ]])]);

    $tools = new OpenAIToolDecisionProvider()->tools('How many members are here?', []);

    expect($tools->map(class_basename(...))->all())->toBe(['CommunityStats']);
});

it('needs no tool when none is selected', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => []])]);

    expect(new OpenAIToolDecisionProvider()->tools('Thanks, Tom!', []))->toBeEmpty();
});

it('fails when OpenAI cannot decide', function (Closure $response, string $exception): void {
    Http::fake(['api.openai.com/v1/decisions' => $response]);

    expect(fn (): Collection => new OpenAIToolDecisionProvider()->tools('@tom hello', []))->toThrow($exception);
})->with([
    'provider error' => [fn () => Http::response('sensitive provider response', 500), RequestException::class],
    'malformed probability' => [fn () => Http::response(['answers' => [['name' => 'SearchCommits', 'type' => 'predicate', 'probability' => [1]]]]), TypeError::class],
]);
