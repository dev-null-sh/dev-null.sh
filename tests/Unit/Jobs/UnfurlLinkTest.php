<?php

declare(strict_types=1);

use App\Jobs\UnfurlLink;
use App\Models\LinkPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

it('is tried once and dropped when its link preview is gone', function (): void {
    Queue::connection('database')->push(new UnfurlLink(LinkPreview::factory()->create()));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload)
        ->maxTries->toBe(1)
        ->deleteWhenMissingModels->toBeTrue();
});
