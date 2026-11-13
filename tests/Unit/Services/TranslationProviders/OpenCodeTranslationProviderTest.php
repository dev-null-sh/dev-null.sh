<?php

declare(strict_types=1);

use App\Exceptions\InvalidTranslation;
use App\Services\TranslationProviders\OpenCodeTranslationProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pest\Expectation;

beforeEach(function (): void {
    config(['ai.providers.opencode.key' => 'test-key']);
});

function openCodeAnswer(string $content): array
{
    return ['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $content]]]];
}

it('translates through DeepSeek on OpenCode without sending account metadata', function (): void {
    Http::fake(['opencode.ai/zen/v1/chat/completions' => Http::response(openCodeAnswer('{"source_language":"de","body":"Good morning"}'))]);

    expect(new OpenCodeTranslationProvider()->translate('Guten morgen', 'en'))->toBe(['source_language' => 'de', 'body' => 'Good morning']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://opencode.ai/zen/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'deepseek-v4.1-flash'
        && $request['thinking'] === ['type' => 'disabled']
        && $request['response_format'] === ['type' => 'json_object']
        && str_contains(collect($request['messages'])->firstWhere('role', 'system')['content'], 'JSON object with exactly two string fields: source_language and body')
        && str_contains(collect($request['messages'])->firstWhere('role', 'system')['content'], 'Translate the post into language code en.')
        && collect($request['messages'])->where('role', 'user')->pluck('content')->all() === ['Guten morgen']);
});

it('only detects the language without one to translate into', function (): void {
    Http::fake(['opencode.ai/*' => Http::response(openCodeAnswer('{"source_language":"de","body":""}'))]);

    expect(new OpenCodeTranslationProvider()->translate('Guten morgen', null))->toBe(['source_language' => 'de', 'body' => '']);

    Http::assertSent(fn (Request $request): bool => str_contains(collect($request['messages'])->firstWhere('role', 'system')['content'], 'Only detect the language; return an empty body.'));
});

it('says why an answer is not a translation', function (string $content, string $reason): void {
    Http::fake(['opencode.ai/*' => Http::response(openCodeAnswer($content))]);

    expect(fn (): array => new OpenCodeTranslationProvider()->translate('Guten morgen', 'en'))
        ->toThrow(fn (InvalidTranslation $exception): Expectation => expect($exception->reason)->toBe($reason)
            ->and($exception->getMessage())->toBe('Invalid translation response.'));
})->with([
    'empty response' => ['', 'empty_or_malformed_response'],
    'malformed JSON' => ['Sensitive provider text', 'empty_or_malformed_response'],
    'missing source' => ['{"body":"Sensitive translation"}', 'invalid_response_shape'],
    'missing body' => ['{"source_language":"de"}', 'invalid_response_shape'],
    'non-string source' => ['{"source_language":["de"],"body":"Sensitive translation"}', 'invalid_response_shape'],
    'non-string body' => ['{"source_language":"de","body":null}', 'invalid_response_shape'],
]);
