<?php

declare(strict_types=1);

use App\Contracts\Services\LinkPreviewProvider;
use App\Jobs\UnfurlLink;
use App\Models\LinkPreview;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

function runNextQueuedJob(): void
{
    test()->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
}

it('fetches the link preview', function (): void {
    $this->mock(LinkPreviewProvider::class)->shouldReceive('metadata')->once()
        ->andReturn(['title' => 'Example', 'description' => null, 'image_url' => null, 'site_name' => null]);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://93.184.216.34/']);

    dispatch_sync(new UnfurlLink($linkPreview));

    expect($linkPreview->refresh())
        ->title->toBe('Example')
        ->fetched_at->not->toBeNull();
});

it('skips link previews that were fetched in the meantime', function (): void {
    $this->mock(LinkPreviewProvider::class)->shouldNotReceive('metadata');

    $linkPreview = LinkPreview::factory()->fetched()->create(['url' => 'https://93.184.216.34/', 'title' => 'Fetched before']);

    dispatch_sync(new UnfurlLink($linkPreview));

    expect($linkPreview->refresh()->title)->toBe('Fetched before');
});

it('retries a page that may be reachable later', function (): void {
    Exceptions::fake();

    $linkPreviews = $this->mock(LinkPreviewProvider::class);
    $linkPreviews->shouldReceive('metadata')->once()->andThrow(new ConnectionException('unreachable'));
    $linkPreviews->shouldReceive('metadata')->once()->andReturn(['title' => 'Back online', 'description' => null, 'image_url' => null, 'site_name' => null]);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://93.184.216.34/']);

    Queue::connection('database')->push(new UnfurlLink($linkPreview));

    runNextQueuedJob();
    $this->travel(59)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh()->fetched_at)->toBeNull();

    $this->travel(1)->seconds();
    runNextQueuedJob();

    expect($linkPreview->refresh())
        ->title->toBe('Back online')
        ->fetched_at->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('marks the link as fetched once the last attempt fails', function (): void {
    Exceptions::fake();

    $this->mock(LinkPreviewProvider::class)->shouldReceive('metadata')->times(3)->andThrow(new ConnectionException('unreachable'));

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
});

it('is dropped when its link preview is gone', function (): void {
    Queue::connection('database')->push(new UnfurlLink(LinkPreview::factory()->create()));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload)->deleteWhenMissingModels->toBeTrue();
});
