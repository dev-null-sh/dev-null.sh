<?php

declare(strict_types=1);

use App\Services\RelevanceProviders\OpenAIRelevanceProvider;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

it('keeps every candidate when optional classification is unavailable', function (int $status, bool $related): void {
    Http::fake([
        'api.openai.com/v1/decisions' => $status === 0
            ? Http::failedConnection()
            : Http::response([], $status),
    ]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('indexing', ['Database indexes', 'Search indexes'], related: $related))
        ->toBe([true, true]);

    Http::assertSentCount(1);
})->with([429, 500, 0])->with([false, true]);

it('gives optional classification a short deadline', function (): void {
    Http::fake([
        'api.openai.com/v1/decisions' => function (Request $request, array $options): PromiseInterface {
            expect($options['timeout'])->toBe(2)
                ->and($options['connect_timeout'])->toBe(1);

            return Http::response(['answers' => [
                ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.9],
                ['type' => 'predicate', 'name' => 'post_2', 'probability' => 0.1],
            ]]);
        },
    ]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('indexing', ['Database indexes', 'A dog photo']))
        ->toBe([true, false]);
});

it('warns about classifier timeouts without logging content or mislabelling other connection failures', function (int $errno, bool $related): void {
    Log::spy();
    Http::fake([
        'api.openai.com/v1/decisions' => Http::failedConnection('cURL error '.$errno.': Provider error with private content'),
    ]);

    expect(new OpenAIRelevanceProvider()->decideRelevance('Private query', ['Private candidate'], related: $related))
        ->toBe([true]);

    if ($errno === 28) {
        Log::shouldHaveReceived('warning')->once()->with('Commit relevance classifier timed out.', ['related' => $related, 'timeout_seconds' => 2]);
    } else {
        Log::shouldNotHaveReceived('warning');
    }
})->with([28, 6])->with([false, true]);
