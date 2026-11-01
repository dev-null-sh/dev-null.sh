<?php

declare(strict_types=1);

use App\Services\CloudflareStream;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.cloudflare_stream' => [
        'account_id' => 'account',
        'api_token' => 'token',
        'customer_subdomain' => 'customer-abc.cloudflarestream.com',
    ]]);
});

it('is configured with an account, a token and a customer subdomain', function (string $missing): void {
    expect(resolve(CloudflareStream::class)->configured())->toBeTrue();

    config(["services.cloudflare_stream.{$missing}" => null]);

    expect(resolve(CloudflareStream::class)->configured())->toBeFalse();
})->with(['account_id', 'api_token', 'customer_subdomain']);

it('creates a live input that records, so its stream can be played', function (): void {
    Http::fake(['api.cloudflare.com/client/v4/accounts/account/stream/live_inputs' => Http::response(['result' => ['uid' => 'input-1']])]);

    expect(resolve(CloudflareStream::class)->createLiveInput('octocat'))->toBe('input-1');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer token')
        && $request->data() === [
            'meta' => ['name' => 'octocat'],
            'recording' => ['mode' => 'automatic'],
            'deleteRecordingAfterDays' => 30,
        ]);
});

it('reads where to send the stream', function (): void {
    Http::fake(['api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-1' => Http::response(['result' => [
        'uid' => 'input-1',
        'rtmps' => ['url' => 'rtmps://live.cloudflare.com:443/live/', 'streamKey' => 'secret'],
    ]])]);

    expect(resolve(CloudflareStream::class)->liveInput('input-1')?->toArray())->toBe([
        'rtmps_url' => 'rtmps://live.cloudflare.com:443/live/',
        'stream_key' => 'secret',
    ]);
});

it('has no live input once it was deleted on Cloudflare', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(status: 404)]);

    expect(resolve(CloudflareStream::class)->liveInput('input-1'))->toBeNull();
});

it('deletes a live input, even one that is already gone', function (int $status): void {
    Http::fake(['api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-1' => Http::response(status: $status)]);

    resolve(CloudflareStream::class)->deleteLiveInput('input-1');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
})->with([200, 404]);

it('throws when Cloudflare fails', function (string $call): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(status: 500)]);
    $stream = resolve(CloudflareStream::class);

    match ($call) {
        'create' => $stream->createLiveInput('octocat'),
        'read' => $stream->liveInput('input-1'),
        'delete' => $stream->deleteLiveInput('input-1'),
    };
})->with(['create', 'read', 'delete'])->throws(RequestException::class);

it('plays a live input in the Cloudflare player', function (): void {
    expect(resolve(CloudflareStream::class)->playerUrl('input-1'))
        ->toBe('https://customer-abc.cloudflarestream.com/input-1/iframe');
});
