<?php

declare(strict_types=1);

use Anthropic\Client;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
        fakeClaude(['topics' => []]);
    })
    ->in('Browser', 'Feature', 'Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    // ..
}

/**
 * Answers every Claude request with the given structured output (no content when null) and returns the requests it received.
 *
 * @param  array<string, mixed>|null  $output
 * @return ArrayObject<int, RequestInterface>
 */
function fakeClaude(?array $output, string $stopReason = 'end_turn'): ArrayObject
{
    $requests = new ArrayObject;

    $handler = function (RequestInterface $request) use ($requests, $output, $stopReason): PromiseInterface {
        $requests->append($request);

        return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $output === null ? [] : [['type' => 'text', 'text' => json_encode($output), 'citations' => null]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ], JSON_THROW_ON_ERROR)));
    };

    app()->instance(Client::class, new Client(apiKey: 'test', requestOptions: [
        'transporter' => new Guzzle(['handler' => HandlerStack::create($handler)]),
        'maxRetries' => 0,
    ]));

    return $requests;
}
