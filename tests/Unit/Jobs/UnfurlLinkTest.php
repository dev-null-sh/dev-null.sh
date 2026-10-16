<?php

declare(strict_types=1);

use App\Jobs\UnfurlLink;
use App\Models\LinkPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function runNextQueuedJob(): void
{
    test()->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
}

it('fetches the link preview', function (): void {
    Http::fake(['https://93.184.216.34/*' => Http::response('<title>Example</title>', 200, ['Content-Type' => 'text/html'])]);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://93.184.216.34/']);

    dispatch_sync(new UnfurlLink($linkPreview));

    expect($linkPreview->refresh())
        ->title->toBe('Example')
        ->fetched_at->not->toBeNull();
});

it('skips link previews that were fetched in the meantime', function (): void {
    Http::fake();

    $linkPreview = LinkPreview::factory()->fetched()->create(['url' => 'https://93.184.216.34/', 'title' => 'Fetched before']);

    dispatch_sync(new UnfurlLink($linkPreview));

    Http::assertNothingSent();

    expect($linkPreview->refresh()->title)->toBe('Fetched before');
});

it('retries a page that may be reachable later', function (): void {
    Exceptions::fake();

    Http::fakeSequence('https://93.184.216.34/*')
        ->pushFailedConnection()
        ->push('<title>Back online</title>', 200, ['Content-Type' => 'text/html']);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://93.184.216.34/']);

    Queue::connection('database')->push(new UnfurlLink($linkPreview));

    runNextQueuedJob();
    $this->travel(59)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh()->fetched_at)->toBeNull();

    Http::assertSentCount(1);

    $this->travel(1)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh())
        ->title->toBe('Back online')
        ->fetched_at->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('marks the link as fetched once the last attempt fails', function (): void {
    Exceptions::fake();

    Http::fake(['https://93.184.216.34/*' => Http::response('<title>Down</title>', 503, ['Content-Type' => 'text/html'])]);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://93.184.216.34/']);

    Queue::connection('database')->push(new UnfurlLink($linkPreview));

    runNextQueuedJob();
    $this->travel(60)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh()->fetched_at)->toBeNull();

    $this->travel(600)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh())
        ->title->toBeNull()
        ->fetched_at->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);

    Http::assertSentCount(3);
});

it('is dropped when its link preview is gone', function (): void {
    Queue::connection('database')->push(new UnfurlLink(LinkPreview::factory()->create()));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload)->deleteWhenMissingModels->toBeTrue();
});
