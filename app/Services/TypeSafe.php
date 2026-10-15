<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

final readonly class TypeSafe
{
    private const int ATTEMPTS = 3;

    private const int MAX_RETRY_DELAY_MS = 5000;

    /**
     * @var list<int>
     */
    private const array RETRYABLE_STATUSES = [408, 429, 500, 502, 503, 504, 529];

    public function isConfigured(): bool
    {
        return filled(config('services.typesafe.key'));
    }

    /**
     * Automatic moderation runs only with a key and the kill switch on, so it stays off in development and tests.
     */
    public function isEnabled(): bool
    {
        return $this->isConfigured() && config()->boolean('services.typesafe.enabled');
    }

    public function model(): string
    {
        return config()->string('services.typesafe.model');
    }

    /**
     * Asks Jev every question about the state in one request. Rate limits, overloads, timeouts, server errors and
     * failed connections are retried with backoff; anything else throws right away.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, array<string, mixed>>  $questions
     * @return array{model: string, request_id: string|null, nouls: array<string, float>, input_tokens: int}
     *
     * @throws ConnectionException|RequestException|UnexpectedValueException
     */
    public function ask(array $state, array $questions): array
    {
        $response = Http::baseUrl(config()->string('services.typesafe.url'))
            ->withToken(config()->string('services.typesafe.key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(config()->integer('services.typesafe.timeout'))
            ->retry(self::ATTEMPTS, $this->retryDelay(...), $this->shouldRetry(...))
            ->post('/v1/systemone', [
                'model' => $this->model(),
                'state' => $state,
                'questions' => $questions,
            ]);

        $nouls = [];

        foreach (array_keys($questions) as $key) {
            $noul = $response->json("answers.{$key}.noul");

            if ((! is_float($noul) && ! is_int($noul)) || $noul < 0 || $noul > 1) {
                throw new UnexpectedValueException("Jev did not answer [{$key}].");
            }

            $nouls[$key] = (float) $noul;
        }

        $model = $response->json('model');
        $inputTokens = $response->json('usage.input_tokens');

        return [
            'model' => is_string($model) ? $model : $this->model(),
            'request_id' => $response->header('x-typesafe-request-id') ?: null,
            'nouls' => $nouls,
            'input_tokens' => is_int($inputTokens) ? $inputTokens : 0,
        ];
    }

    private function shouldRetry(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && in_array($exception->response->status(), self::RETRYABLE_STATUSES, true));
    }

    private function retryDelay(int $attempt, Throwable $exception): int
    {
        $delay = 500 * 2 ** ($attempt - 1);

        if ($exception instanceof RequestException) {
            $retryAfterMs = $exception->response->header('retry-after-ms');
            $retryAfter = $exception->response->header('Retry-After');

            $delay = match (true) {
                is_numeric($retryAfterMs) => (int) $retryAfterMs,
                is_numeric($retryAfter) => (int) ((float) $retryAfter * 1000),
                default => $delay,
            };
        }

        return min($delay, self::MAX_RETRY_DELAY_MS);
    }
}
