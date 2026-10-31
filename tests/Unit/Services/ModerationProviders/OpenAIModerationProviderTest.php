<?php

declare(strict_types=1);

use App\Services\ModerationProviders\OpenAIModerationProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['ai.providers.openai.key' => 'sk-test']);
});

/**
 * @param  list<string>  $flagged
 * @param  array<string, float>  $scores
 * @return array{results: list<array{flagged: bool, categories: array<string, bool>, category_scores: array<string, float>}>}
 */
function openAIModerationResponse(array $flagged = [], array $scores = []): array
{
    $categories = ['harassment', 'violence', 'sexual', 'sexual/minors'];

    return ['results' => [[
        'flagged' => $flagged !== [],
        'categories' => array_combine($categories, array_map(fn (string $category): bool => in_array($category, $flagged, true), $categories)),
        'category_scores' => array_combine($categories, array_map(fn (string $category): float => $scores[$category] ?? (in_array($category, $flagged, true) ? 0.9 : 0.0), $categories)),
    ]]];
}

/**
 * @return array{status: string, output: list<array{type: string, role: string, content: list<array<string, string>>}>}
 */
function openAIHarmfulResponse(bool|string $answer): array
{
    $content = is_bool($answer)
        ? ['type' => 'output_text', 'text' => json_encode(['harmful' => $answer])]
        : ['type' => 'refusal', 'refusal' => $answer];

    return ['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [$content]]]];
}

it('flags nothing in text openai does not flag', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(openAIModerationResponse())]);

    expect(new OpenAIModerationProvider()->moderate('Shipped a new feature'))->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/moderations'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'omni-moderation-latest'
        && $request['input'] === [['type' => 'text', 'text' => 'Shipped a new feature']]);
    Http::assertSentCount(1);
});

it('gives the categories openai flags with their scores', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(openAIModerationResponse(['harassment', 'violence'], ['harassment' => 0.95]))]);

    expect(new OpenAIModerationProvider()->moderate('A post'))->toBe(['harassment' => 0.95, 'violence' => 0.9]);
});

it('flags categories scoring above the strict thresholds, even when openai does not flag them', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(openAIModerationResponse(scores: [
        'harassment' => 0.2,
        'violence' => 0.19,
        'sexual/minors' => 0.01,
    ]))]);

    expect(new OpenAIModerationProvider()->moderate('A post'))->toBe(['harassment' => 0.2, 'sexual/minors' => 0.01]);
});

it('checks the text with the first image, and every other image on its own, keeping the highest score', function (): void {
    Http::fakeSequence('api.openai.com/v1/moderations')
        ->push(openAIModerationResponse(['sexual'], ['sexual' => 0.5]))
        ->push(openAIModerationResponse(['sexual'], ['sexual' => 0.7]));

    $first = 'data:image/webp;base64,'.base64_encode('first');
    $second = 'data:image/webp;base64,'.base64_encode('second');

    expect(new OpenAIModerationProvider()->moderate('Look at this', [$first, $second]))->toBe(['sexual' => 0.7]);

    expect(Http::recorded()->map(fn (array $pair): mixed => $pair[0]['input'])->all())->toBe([
        [['type' => 'text', 'text' => 'Look at this'], ['type' => 'image_url', 'image_url' => ['url' => $first]]],
        [['type' => 'image_url', 'image_url' => ['url' => $second]]],
    ]);
});

it('checks only the images when there is no text', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(openAIModerationResponse())]);

    $image = 'data:image/webp;base64,'.base64_encode('first');

    expect(new OpenAIModerationProvider()->moderate('', [$image]))->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request['input'] === [['type' => 'image_url', 'image_url' => ['url' => $image]]]);
    Http::assertSentCount(1);
});

it('asks openai nothing for nothing to moderate', function (): void {
    Http::fake();

    expect(new OpenAIModerationProvider()->moderate(''))->toBe([]);

    Http::assertNothingSent();
});

it('fails when openai refuses to moderate', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response([], 500)]);

    expect(fn (): array => new OpenAIModerationProvider()->moderate('A post'))->toThrow(RequestException::class);
});

it('asks the reasoning model whether text is harmful', function (bool $harmful): void {
    Http::fake(['api.openai.com/v1/responses' => Http::response(openAIHarmfulResponse($harmful))]);

    expect(new OpenAIModerationProvider()->isHarmful('I have a bomb in my bag'))->toBe($harmful);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request['model'] === 'gpt-6.1-sol'
        && $request['text']['format']['schema']['required'] === ['harmful']
        && $request['input'][1]['content'] === [['type' => 'input_text', 'text' => 'I have a bomb in my bag']]);
})->with([true, false]);

it('counts anything but a clear no as harmful', function (array $response): void {
    Http::fake(['api.openai.com/v1/responses' => Http::response($response)]);

    expect(new OpenAIModerationProvider()->isHarmful('A post'))->toBeTrue();
})->with([
    'a refusal' => [openAIHarmfulResponse("I'm sorry, I cannot assist with that request.")],
    'unreadable text' => [['status' => 'incomplete', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => '{"harm']]]]]],
    'no output' => [['status' => 'completed', 'output' => []]],
]);
