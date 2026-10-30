<?php

declare(strict_types=1);

use App\Services\RelevanceProviders\OpenAIRelevanceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('asks the decision model about every post, one predicate each', function (): void {
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::response(relevanceResponse([true, false]))]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('indexing strategies', ['About indexing', 'About something else']))->toBe([true, false]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/decisions'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'gpt-6-luna'
        && str_ends_with((string) $request['input'], "Query: indexing strategies\n\nPosts:\n1. About indexing\n2. About something else")
        && array_column($request['questions'], 'name') === ['post_1', 'post_2']
        && array_unique(array_column($request['questions'], 'type')) === ['predicate']
        && count(array_filter(array_column($request['questions'], 'instructions'))) === 2);
});

it('keeps loosely related posts at or above the relevance cutoff', function (float $probability, bool $isKept): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['type' => 'predicate', 'name' => 'post_1', 'probability' => $probability],
    ]])]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('a query', ['A post']))->toBe([$isKept]);
})->with([
    'likely' => [0.8, true],
    'even' => [0.5, true],
    'loosely related' => [0.4, true],
    'at the cutoff' => [0.3, true],
    'below the cutoff' => [0.299, false],
    'unlikely' => [0.2, false],
]);

it('keeps every post when the decision model gives no clear answer', function (array $body): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response($body)]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('a query', ['A post']))->toBe([true]);
})->with([
    'a refusal' => [['answers' => [['type' => 'refusal', 'name' => 'post_1']]]],
    'no answers' => [[]],
    'answers that are not a list' => [['answers' => 'no']],
    'an answer for another post' => [['answers' => [['type' => 'predicate', 'name' => 'post_2', 'probability' => 0.0]]]],
    'an answer that is not an object' => [['answers' => ['no']]],
    'a probability that is not a number' => [['answers' => [['type' => 'predicate', 'name' => 'post_1', 'probability' => 'low']]]],
]);

it('asks openai nothing for nothing to judge', function (): void {
    Http::fake();

    expect(new OpenAIRelevanceProvider()->decideRelevance('a query', []))->toBe([]);

    Http::assertNothingSent();
});

it('fails when openai refuses', function (): void {
    Http::fake(['api.openai.com/v1/decisions' => Http::response([], 500)]);

    expect(fn (): array => new OpenAIRelevanceProvider()->decideRelevance('a query', ['A post']))->toThrow(RequestException::class);
});
