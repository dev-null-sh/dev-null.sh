<?php

declare(strict_types=1);

use App\Services\TypeSafe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'services.typesafe.key' => 'test-key',
        'services.typesafe.url' => 'https://api.typesafe.ai',
        'services.typesafe.model' => 'jev-1.13.0',
        'services.typesafe.enabled' => true,
    ]);
});

$questions = ['rule_spam' => ['type' => 'noul', 'instructions' => 'Is `commit` spam?']];

it('asks jev the questions about the state', function () use ($questions): void {
    Http::fake([
        'api.typesafe.ai/v1/systemone' => Http::response(jevAnswers(['rule_spam' => 0.97]), 200, ['x-typesafe-request-id' => 'req_123']),
    ]);

    $answers = resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Buy stars']], $questions);

    expect($answers)->toBe([
        'model' => 'jev-1.13.0',
        'request_id' => 'req_123',
        'nouls' => ['rule_spam' => 0.97],
        'input_tokens' => 1646,
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.typesafe.ai/v1/systemone'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->data() === [
            'model' => 'jev-1.13.0',
            'state' => ['commit' => ['body' => 'Buy stars']],
            'questions' => $questions,
        ]);
});

it('accepts whole probabilities and missing usage', function () use ($questions): void {
    Http::fake(['api.typesafe.ai/*' => Http::response(['answers' => ['rule_spam' => ['noul' => 1]]])]);

    $answers = resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Buy stars']], $questions);

    expect($answers)->toBe([
        'model' => 'jev-1.13.0',
        'request_id' => null,
        'nouls' => ['rule_spam' => 1.0],
        'input_tokens' => 0,
    ]);
});

it('retries when rate limited or overloaded, honoring retry after', function (int $status, array $headers, int $sleptMicroseconds) use ($questions): void {
    Http::fake([
        'api.typesafe.ai/*' => Http::sequence()
            ->push(['detail' => 'Slow down'], $status, $headers)
            ->push(jevAnswers(['rule_spam' => 0.1])),
    ]);

    $answers = resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions);

    expect($answers['nouls'])->toBe(['rule_spam' => 0.1]);

    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::usleep($sleptMicroseconds)]);
})->with([
    'rate limited' => [429, ['Retry-After' => '2'], 2_000_000],
    'rate limited in milliseconds' => [429, ['retry-after-ms' => '1200'], 1_200_000],
    'overloaded' => [529, [], 500_000],
    'timed out' => [408, [], 500_000],
    'unavailable' => [503, [], 500_000],
    'asked to wait too long' => [429, ['Retry-After' => '120'], 5_000_000],
]);

it('retries failed connections', function () use ($questions): void {
    Http::fake([
        'api.typesafe.ai/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(jevAnswers(['rule_spam' => 0.1])),
    ]);

    $answers = resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions);

    expect($answers['nouls'])->toBe(['rule_spam' => 0.1]);
});

it('gives up after three attempts', function () use ($questions): void {
    Http::fake(['api.typesafe.ai/*' => Http::response(['detail' => 'Overloaded'], 529)]);

    expect(fn (): array => resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions))
        ->toThrow(RequestException::class);

    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::usleep(500_000), Sleep::usleep(1_000_000)]);
});

it('gives up on failed connections after three attempts', function () use ($questions): void {
    Http::fake(['api.typesafe.ai/*' => Http::failedConnection()]);

    expect(fn (): array => resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions))
        ->toThrow(ConnectionException::class);
});

it('does not retry requests that can never succeed', function (int $status) use ($questions): void {
    Http::fake(['api.typesafe.ai/*' => Http::response(['detail' => 'Nope'], $status)]);

    expect(fn (): array => resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
})->with([400, 401, 413, 422]);

it('rejects answers it did not ask for', function (array $body) use ($questions): void {
    Http::fake(['api.typesafe.ai/*' => Http::response($body)]);

    expect(fn (): array => resolve(TypeSafe::class)->ask(['commit' => ['body' => 'Hello']], $questions))
        ->toThrow(UnexpectedValueException::class, 'Jev did not answer [rule_spam].');
})->with([
    'no answers' => [['model' => 'jev-1.13.0']],
    'a missing answer' => [['answers' => ['rule_other' => ['noul' => 0.5]]]],
    'a choice instead of a noul' => [['answers' => ['rule_spam' => ['choice' => 'yes']]]],
    'a probability above one' => [['answers' => ['rule_spam' => ['noul' => 1.2]]]],
    'a negative probability' => [['answers' => ['rule_spam' => ['noul' => -0.1]]]],
    'a string probability' => [['answers' => ['rule_spam' => ['noul' => '0.5']]]],
]);

it('is enabled only with a key and the kill switch on', function (?string $key, bool $enabled, bool $isConfigured, bool $isEnabled): void {
    config(['services.typesafe.key' => $key, 'services.typesafe.enabled' => $enabled]);

    $typeSafe = resolve(TypeSafe::class);

    expect($typeSafe->isConfigured())->toBe($isConfigured)
        ->and($typeSafe->isEnabled())->toBe($isEnabled);
})->with([
    'a key and enabled' => ['test-key', true, true, true],
    'a key but disabled' => ['test-key', false, true, false],
    'no key' => [null, true, false, false],
    'an empty key' => ['', true, false, false],
]);

it('has the pinned model', function (): void {
    expect(resolve(TypeSafe::class)->model())->toBe('jev-1.13.0');
});
